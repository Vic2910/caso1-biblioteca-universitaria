<?php

declare(strict_types=1);

namespace App\Services\Prestamos;

use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único lugar donde viven las reglas de préstamo (specs/004-gestion-prestamos,
 * D-019, Principio III): disponibilidad, límite de tres no devueltos por
 * carné, vencimiento automático y atomicidad de registrar/devolver (D-020,
 * D-022, D-026). Ni el controlador ni las vistas reimplementan estas reglas.
 *
 * [CONCEPTO] Reglas de préstamo:
 *  - Registrar descuenta exactamente un ejemplar disponible; no se puede
 *    prestar un libro sin disponibilidad («No hay ejemplares disponibles de
 *    este libro.»).
 *  - Un estudiante (carné tras `trim()`) no puede tener más de tres préstamos
 *    sin devolver; los `vencido` cuentan como no devueltos.
 *  - Devolver suma la disponibilidad exactamente una vez; un préstamo
 *    `devuelto` no se devuelve dos veces.
 *  - `vencido` se calcula con prevista estrictamente anterior a hoy (desde el
 *    día siguiente) y se sincroniza al registrar, devolver y listar; los
 *    `devuelto` nunca cambian. Los préstamos son inmutables salvo su
 *    devolución (FR-007).
 */
class PrestamoService
{
    /**
     * Marca como `vencido` todo préstamo `activo` sin devolución real cuya
     * devolución prevista sea estrictamente anterior a hoy (vencido desde el
     * día siguiente). Jamás toca filas `devuelto` (estado final, D-020).
     */
    public function sincronizarVencidos(): void
    {
        Prestamo::query()
            ->where('estado', Prestamo::ESTADO_ACTIVO)
            ->whereNull('fecha_devolucion_real')
            ->whereDate('fecha_devolucion_prevista', '<', today())
            ->update(['estado' => Prestamo::ESTADO_VENCIDO]);
    }

    /**
     * Comprueba la regla R1: el libro tiene al menos un ejemplar disponible.
     */
    public function libroDisponible(Libro $libro): bool
    {
        return $libro->ejemplares_disponibles >= 1;
    }

    /**
     * Cuenta los préstamos no devueltos (`activo` + `vencido`, D-026) de un
     * carné comparado tras `trim()` exclusivo (D-023).
     */
    public function prestamosNoDevueltos(string $carnet): int
    {
        return Prestamo::query()
            ->where('carnet_estudiante', trim($carnet))
            ->whereIn('estado', [Prestamo::ESTADO_ACTIVO, Prestamo::ESTADO_VENCIDO])
            ->count();
    }

    /**
     * Registra el préstamo y descuenta un ejemplar en una única transacción
     * con bloqueo de fila del libro (D-022): re-verifica disponibilidad (R1)
     * y límite de tres no devueltos (R2) bajo el bloqueo, aplica el
     * descuento condicional (nunca por debajo de cero, D-022) y crea el
     * préstamo con estado `activo` (SC-001, SC-002).
     *
     * @param  array<string, mixed>  $datosValidados
     *
     * @throws ValidationException Si el libro se quedó sin disponibilidad o el
     *                             estudiante superó el límite entre la
     *                             validación y la transacción (errores junto
     *                             al campo afectado).
     */
    public function registrar(array $datosValidados): Prestamo
    {
        return DB::transaction(function () use ($datosValidados): Prestamo {
            $libro = Libro::query()
                ->whereKey($datosValidados['libro_id'])
                ->lockForUpdate()
                ->first();

            if ($libro === null || ! $this->libroDisponible($libro)) {
                throw ValidationException::withMessages([
                    'libro_id' => 'No hay ejemplares disponibles de este libro.',
                ]);
            }

            if ($this->prestamosNoDevueltos((string) $datosValidados['carnet_estudiante']) >= 3) {
                throw ValidationException::withMessages([
                    'carnet_estudiante' => 'El estudiante ya tiene el máximo de tres préstamos sin devolver.',
                ]);
            }

            Libro::query()
                ->whereKey($libro->getKey())
                ->where('ejemplares_disponibles', '>', 0)
                ->decrement('ejemplares_disponibles');

            $prestamo = Prestamo::create([
                'libro_id' => $libro->getKey(),
                'nombre_estudiante' => $datosValidados['nombre_estudiante'],
                'carnet_estudiante' => $datosValidados['carnet_estudiante'],
                'fecha_prestamo' => $datosValidados['fecha_prestamo'],
                'fecha_devolucion_prevista' => $datosValidados['fecha_devolucion_prevista'],
                'fecha_devolucion_real' => null,
                'estado' => Prestamo::ESTADO_ACTIVO,
            ]);

            $this->sincronizarVencidos();

            return $prestamo;
        });
    }

    /**
     * Registra la devolución en una única transacción con bloqueo de fila del
     * préstamo (D-022): un préstamo ya `devuelto` se rechaza sin tocar la
     * disponibilidad (M2, SC-001 «nunca +2»); en caso contrario fija la fecha
     * real, pasa el estado a `devuelto` y suma el ejemplar exactamente una
     * vez (R4, R8).
     *
     * @throws ValidationException Si el préstamo ya fue devuelto.
     */
    public function devolver(Prestamo $prestamo, string $fechaReal): Prestamo
    {
        return DB::transaction(function () use ($prestamo, $fechaReal): Prestamo {
            /** @var Prestamo $registro */
            $registro = Prestamo::query()
                ->whereKey($prestamo->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($registro->devuelto()) {
                throw ValidationException::withMessages([
                    'devolucion' => 'Este préstamo ya fue devuelto.',
                ]);
            }

            $registro->update([
                'fecha_devolucion_real' => $fechaReal,
                'estado' => Prestamo::ESTADO_DEVUELTO,
            ]);

            Libro::query()->whereKey($registro->libro_id)->increment('ejemplares_disponibles');

            $this->sincronizarVencidos();

            return $registro;
        });
    }
}
