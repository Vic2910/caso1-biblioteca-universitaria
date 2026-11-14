<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Libro;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Consulta de solo lectura del catálogo (specs/002-consulta-catalogo, D-010):
 * listado paginado en GET /catalogo y ficha de detalle en GET /catalogo/{id}.
 * Desde specs/006-busqueda-catalogo (D-028/D-029/D-031) el listado admite los
 * criterios `q` (texto libre) y `categoria` (filtro exacto) sobre la misma ruta.
 */
class CatalogoController extends Controller
{
    /**
     * Libros por página del listado (FR-005).
     */
    private const LIBROS_POR_PAGINA = 10;

    /**
     * Muestra el listado paginado del catálogo ordenado A–Z por título
     * ignorando tildes y mayúsculas (desempate: autor, después ISBN),
     * filtrado por los criterios activos de la consulta (006, D-028).
     */
    public function index(Request $request): View
    {
        if (! $this->paginaEsValida($request->query('page'))) {
            abort(404, 'Página no válida');
        }

        $texto = $this->textoDeBusqueda($request->query('q'));
        $categoria = $this->categoriaDeFiltro($request->query('categoria'));

        $consulta = Libro::with('categoria')
            ->when($texto !== '', fn (Builder $consulta) => $this->aplicarTexto($consulta, $texto))
            ->when($categoria !== null, fn (Builder $consulta) => $consulta->where('categoria_id', $categoria));

        $libros = $consulta
            ->orderBy('titulo')
            ->orderBy('autor')
            ->orderBy('isbn')
            ->paginate(self::LIBROS_POR_PAGINA)
            ->withQueryString();

        // La página se valida sobre el total FILTRADO (D-034): con criterios
        // activos solo existen las páginas que caben en sus resultados.
        if ($libros->currentPage() > $libros->lastPage()) {
            abort(404, 'Página no válida');
        }

        return view('catalogo.index', [
            'libros' => $libros,
            'q' => $texto,
            'categoria' => $categoria,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'hayCriterio' => $texto !== '' || $categoria !== null,
        ]);
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

    /**
     * Texto de búsqueda normalizado: se recorta y se vacían los valores que
     * no aportan criterio (D-029). Solo se acepta cadena; cualquier otro tipo
     * de parámetro se ignora como criterio.
     */
    private function textoDeBusqueda(mixed $texto): string
    {
        if (! is_string($texto)) {
            return '';
        }

        return trim($texto);
    }

    /**
     * Filtro de categoría: solo un identificador entero ≥ 1 existente en la
     * base sirve de criterio; el resto (letras, negativos, decimales,
     * inexistentes) se ignora y no restringe el listado (D-031).
     */
    private function categoriaDeFiltro(mixed $categoria): ?int
    {
        if (! is_string($categoria) || preg_match('/^\d+$/', $categoria) !== 1) {
            return null;
        }

        $identificador = (int) $categoria;

        if ($identificador < 1 || ! Categoria::whereKey($identificador)->exists()) {
            return null;
        }

        return $identificador;
    }

    /**
     * Coincidencia que contiene sobre titulo, autor, editorial e ISBN con
     * regla OR entre los cuatro campos (D-029). Los comodines `LIKE` del
     * texto del usuario se escapean con bindings —sin `whereRaw`— para que
     * `%` y `_` se busquen literalmente (Principio I).
     */
    private function aplicarTexto(Builder $consulta, string $texto): Builder
    {
        $patron = '%'.addcslashes($texto, '\\%_').'%';

        return $consulta->where(function (Builder $grupo) use ($patron) {
            $grupo->where('titulo', 'like', $patron)
                ->orWhere('autor', 'like', $patron)
                ->orWhere('editorial', 'like', $patron)
                ->orWhere('isbn', 'like', $patron);
        });
    }
}
