<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Libro extends Model
{
    use HasFactory;

    /**
     * Campos asignables masivamente.
     *
     * `ejemplares_disponibles` queda fuera a propósito: es un valor derivado
     * que solo calcula el servidor (specs/003-gestion-libros, D-023, FR-007).
     *
     * @var list<string>
     */
    protected $fillable = [
        'isbn',
        'titulo',
        'autor',
        'editorial',
        'anio_publicacion',
        'ejemplares_totales',
        'categoria_id',
        'portada',
    ];

    /**
     * Reglas del modelo: ningún guardado deja el invariante de disponibilidad
     * roto (specs/003-gestion-libros, D-023, Principio III).
     */
    protected static function booted(): void
    {
        static::saving(function (Libro $libro): void {
            $libro->recalcularDisponibilidad();
        });

        static::deleting(function (Libro $libro): void {
            $libro->eliminarPortadas();
        });
    }

    /**
     * Atributos con conversión de tipos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'anio_publicacion' => 'integer',
        'ejemplares_totales' => 'integer',
        'ejemplares_disponibles' => 'integer',
    ];

    /**
     * Número de préstamos activos registrados para este libro.
     */
    public function prestamosActivos(): int
    {
        return $this->prestamos()->where('estado', Prestamo::ESTADO_ACTIVO)->count();
    }

    /**
     * Elimina de `portadas/{id}` todo archivo cuya ruta difiera de la
     * referencia vigente en la base de datos (specs/005-gestion-portadas,
     * D-020): es el paso final de las transiciones T1, T2 y T3 y la
     * garantía de ausencia de huérfanos incluso ante reemplazos
     * superpuestos (SC-005).
     *
     * Tolera fallos de disco: los registra y no lanza, para que un problema
     * transitorio de Storage no invalide la operación en curso (D-025).
     */
    public function reconciliarPortada(): void
    {
        try {
            $disco = Storage::disk('public');

            foreach ($disco->files("portadas/{$this->id}") as $archivo) {
                if ($archivo !== $this->portada) {
                    $disco->delete($archivo);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo reconciliar el directorio de portadas del libro '.$this->id, [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Transición T5 (specs/005, D-020/FR-004/SC-005): al eliminar el libro
     * borra su directorio `portadas/{id}/` completo y, si la referencia
     * vigente queda fuera de ese directorio (portadas dadas de alta en
     * `003` con `store('portadas')`), también ese archivo, para que
     * ninguna vía de borrado deje archivos huérfanos.
     *
     * Tolera fallos de disco (D-022/D-025): se registran y no impiden
     * eliminar el libro.
     */
    public function eliminarPortadas(): void
    {
        try {
            $disco = Storage::disk('public');
            $disco->deleteDirectory("portadas/{$this->id}");

            if ($this->portada !== null) {
                $disco->delete($this->portada);
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudieron eliminar las portadas del libro '.$this->id, [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Recalcula la disponibilidad derivada: totales − préstamos activos.
     * Se ejecuta en el evento `saving`, de modo que todo camino de escritura
     * de la aplicación mantiene el invariante (specs/003, D-023, FR-007).
     */
    public function recalcularDisponibilidad(): void
    {
        $this->ejemplares_disponibles = max(0, $this->ejemplares_totales - $this->prestamosActivos());
    }

    /**
     * Categoría a la que pertenece el libro.
     *
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Préstamos registrados para este libro.
     *
     * @return HasMany<Prestamo, $this>
     */
    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class);
    }
}
