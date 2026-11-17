<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DevolverPrestamoRequest;
use App\Http\Requests\RegistrarPrestamoRequest;
use App\Models\Libro;
use App\Models\Prestamo;
use App\Services\Prestamos\PrestamoService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

/**
 * Préstamos (specs/004-gestion-prestamos, D-018): registrar, consultar y
 * devolver. Controlador delgado (Principio III): toda regla de préstamo vive
 * en `PrestamoService` (D-019) y la validación en Form Requests (D-021).
 * No existen acciones de edición ni eliminación: el historial es inmutable
 * (FR-007).
 */
class PrestamoController extends Controller
{
    /**
     * Préstamos por página del listado (D-024).
     */
    private const PRESTAMOS_POR_PAGINA = 10;

    public function __construct(private readonly PrestamoService $prestamos) {}

    /**
     * Listado paginado de todos los préstamos, más recientes primero. El
     * estado se sincroniza antes de listar para no mostrar vencidos
     * desactualizados (D-020).
     */
    public function index(): View
    {
        $this->prestamos->sincronizarVencidos();

        $prestamos = Prestamo::query()
            ->with('libro')
            ->orderByDesc('fecha_prestamo')
            ->orderByDesc('id')
            ->paginate(self::PRESTAMOS_POR_PAGINA)
            ->withQueryString();

        return view('prestamos.index', ['prestamos' => $prestamos]);
    }

    /**
     * Formulario de registro con el selector de libros (incluidos los
     * agotados, D-027) y la fecha de préstamo por defecto de hoy.
     */
    public function create(): View
    {
        return view('prestamos.create', [
            'libros' => $this->libros(),
            'fechaPrestamo' => today()->format('Y-m-d'),
        ]);
    }

    /**
     * Registra el préstamo validado y redirige al listado con mensaje de
     * éxito (D-018). Los rechazos de carrera que llegan del servicio vuelven
     * al formulario con el error junto al campo (SC-002).
     */
    public function store(RegistrarPrestamoRequest $request): RedirectResponse
    {
        $this->prestamos->registrar($request->validated());

        $this->prestamos->sincronizarVencidos();

        return redirect()
            ->route('prestamos.index')
            ->with('success', 'Préstamo registrado correctamente.');
    }

    /**
     * Formulario de devolución con el resumen del préstamo; el estado se
     * sincroniza antes de mostrarlo (D-020). Préstamo inexistente → 404 en
     * español con la vista compartida de `002` (D-018).
     */
    public function devolver(string $id): View
    {
        $prestamo = $this->resolverPrestamo($id);

        $this->prestamos->sincronizarVencidos();

        return view('prestamos.devolucion', ['prestamo' => $prestamo->fresh()]);
    }

    /**
     * Registra la devolución validada y redirige al listado con mensaje de
     * éxito (D-018). Un préstamo ya devuelto vuelve al formulario con el
     * error M2 junto a la acción, sin tocar la disponibilidad (SC-001).
     */
    public function registrarDevolucion(DevolverPrestamoRequest $request, string $id): RedirectResponse
    {
        $prestamo = $this->resolverPrestamo($id);

        $this->prestamos->devolver($prestamo, (string) $request->validated()['fecha_devolucion_real']);

        $this->prestamos->sincronizarVencidos();

        return redirect()
            ->route('prestamos.index')
            ->with('success', 'Devolución registrada correctamente.');
    }

    /**
     * Libros ordenados por título para el selector del formulario.
     *
     * @return Collection<int, Libro>
     */
    private function libros(): Collection
    {
        return Libro::query()->orderBy('titulo')->get();
    }

    /**
     * Resuelve el préstamo por su id numérico o responde 404 en español con
     * la vista compartida de `002` (mecanismo D-013, D-018).
     */
    private function resolverPrestamo(string $id): Prestamo
    {
        $prestamo = Prestamo::find($id);

        if ($prestamo === null) {
            abort(404, 'Préstamo no encontrado');
        }

        return $prestamo;
    }
}
