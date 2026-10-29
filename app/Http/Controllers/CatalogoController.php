<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Libro;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Consulta de solo lectura del catálogo (specs/002-consulta-catalogo, D-010):
 * listado paginado en GET /catalogo y ficha de detalle en GET /catalogo/{id}.
 */
class CatalogoController extends Controller
{
    /**
     * Libros por página del listado (FR-005).
     */
    private const LIBROS_POR_PAGINA = 10;

    /**
     * Muestra el listado paginado del catálogo ordenado A–Z por título
     * ignorando tildes y mayúsculas (desempate: autor, después ISBN).
     */
    public function index(Request $request): View
    {
        if (! $this->paginaEsValida($request->query('page'))) {
            abort(404, 'Página no válida');
        }

        $libros = Libro::with('categoria')
            ->orderBy('titulo')
            ->orderBy('autor')
            ->orderBy('isbn')
            ->paginate(self::LIBROS_POR_PAGINA)
            ->withQueryString();

        if ($libros->currentPage() > $libros->lastPage()) {
            abort(404, 'Página no válida');
        }

        return view('catalogo.index', ['libros' => $libros]);
    }

    /**
     * Muestra la ficha completa de un libro con su categoría.
     */
    public function show(string $id): View
    {
        $libro = Libro::with('categoria')->find($id);

        if ($libro === null) {
            abort(404, 'Libro no encontrado');
        }

        return view('catalogo.show', ['libro' => $libro]);
    }

    /**
     * El parámetro crudo `page` debe ser ausente, vacío o una cadena de
     * dígitos cuyo valor entero sea ≥ 1 (contrato http-endpoints.md §Parámetros).
     */
    private function paginaEsValida(mixed $pagina): bool
    {
        if ($pagina === null || $pagina === '') {
            return true;
        }

        if (! is_string($pagina) || preg_match('/^\d+$/', $pagina) !== 1) {
            return false;
        }

        return (int) $pagina >= 1;
    }
}
