<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Contracts\View\View;

/**
 * Panel de indicadores de solo consulta (specs/007-panel-biblioteca, D-018):
 * una única acción que resume el ranking de préstamos, los vencidos del día y
 * la disponibilidad por categoría con los registros vigentes al consultar.
 */
class PanelController extends Controller
{
    /**
     * Calcula los tres indicadores con una sola fecha de consulta (D-022) y
     * tres consultas agregadas (D-023); la vista solo los presenta.
     */
    public function index(): View
    {
        $fechaConsulta = today();

        $ranking = Libro::has('prestamos')
            ->withCount('prestamos')
            ->orderByDesc('prestamos_count')
            ->orderBy('titulo')
            ->orderBy('isbn')
            ->limit(5)
            ->get()
            ->map(fn (Libro $libro): array => [
                'titulo' => $libro->titulo,
                'autor' => $libro->autor,
                'prestamos' => (int) $libro->prestamos_count,
            ]);

        $vencidos = Prestamo::query()
            ->with('libro')
            ->whereNull('fecha_devolucion_real')
            ->where('fecha_devolucion_prevista', '<', $fechaConsulta)
            ->join('libros', 'libros.id', '=', 'prestamos.libro_id')
            ->orderBy('fecha_devolucion_prevista')
            ->orderBy('libros.titulo')
            ->orderBy('prestamos.id')
            ->get(['prestamos.*'])
            ->map(fn (Prestamo $prestamo): array => [
                'libro' => $prestamo->libro->titulo,
                'estudiante' => $prestamo->nombre_estudiante,
                'carnet' => $prestamo->carnet_estudiante,
                'fecha_prestamo' => $prestamo->fecha_prestamo->toDateString(),
                'fecha_devolucion_prevista' => $prestamo->fecha_devolucion_prevista->toDateString(),
                'dias_retraso' => (int) $prestamo->fecha_devolucion_prevista->diffInDays($fechaConsulta),
            ]);

        $disponibilidad = Categoria::query()
            ->withCount('libros')
            ->withSum('libros as ejemplares_disponibles', 'ejemplares_disponibles')
            ->orderBy('nombre')
            ->having('libros_count', '>', 0)
            ->get()
            ->map(fn (Categoria $categoria): array => [
                'nombre' => $categoria->nombre,
                'disponibles' => (int) $categoria->ejemplares_disponibles,
            ]);

        return view('panel.index', [
            'ranking' => $ranking,
            'vencidos' => $vencidos,
            'disponibilidad' => $disponibilidad,
        ]);
    }
}
