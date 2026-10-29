<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaStoreRequest;
use App\Http\Requests\CategoriaUpdateRequest;
use App\Models\Categoria;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD de categorías (specs/003-gestion-libros, D-018): vista propia en
 * `/categorias`. Toda escritura pasa por Form Requests y `validated()`
 * (Principio II); la eliminación replica las tres capas de D-022 y solo
 * existe la ruta DELETE (FR-008, SC-007).
 */
class CategoriaController extends Controller
{
    /**
     * Listado de categorías con la cantidad de libros asociados como
     * referencia (contracts/ui-structure §5).
     */
    public function index(): View
    {
        return view('categorias.index', [
            'categorias' => Categoria::query()->withCount('libros')->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Formulario de alta con un único campo «Nombre».
     */
    public function create(): View
    {
        return view('categorias.create');
    }

    /**
     * Registra la categoría validada y redirige al listado con mensaje de éxito.
     */
    public function store(CategoriaStoreRequest $request): RedirectResponse
    {
        Categoria::create($request->validated());

        return redirect()
            ->route('categorias.index')
            ->with('success', 'Categoría creada correctamente.');
    }

    /**
     * Formulario de edición precargado; categoría inexistente → 404 en español.
     */
    public function edit(string $categoria): View
    {
        return view('categorias.edit', [
            'categoria' => $this->resolverCategoria($categoria),
        ]);
    }

    /**
     * Actualiza la categoría validada (el Form Request ignora el propio
     * registro en la regla unique para permitir conservar su nombre).
     */
    public function update(CategoriaUpdateRequest $request, string $categoria): RedirectResponse
    {
        $modelo = $this->resolverCategoria($categoria);

        $modelo->fill($request->validated())->save();

        return redirect()
            ->route('categorias.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Elimina la categoría con las tres capas de D-022 (specs/003-gestion-libros):
     * comprobación previa de libros asociados, respaldo `RESTRICT` de la base de
     * datos ante carreras, y confirmación previa en el cliente. Solo existe la
     * ruta DELETE: GET nunca elimina (FR-005, SC-003).
     */
    public function destroy(string $categoria): RedirectResponse
    {
        $modelo = $this->resolverCategoria($categoria);

        if ($modelo->libros()->exists()) {
            return redirect()
                ->route('categorias.index')
                ->with('error', 'La categoría tiene libros asociados y no puede eliminarse.');
        }

        try {
            $modelo->delete();
        } catch (QueryException) {
            return redirect()
                ->route('categorias.index')
                ->with('error', 'No se pudo eliminar el registro. Inténtelo de nuevo.');
        }

        return redirect()
            ->route('categorias.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }

    /**
     * Resuelve la categoría por su id numérico o responde 404 en español
     * con la vista compartida de `002` (mecanismo D-013).
     */
    private function resolverCategoria(string $id): Categoria
    {
        $categoria = Categoria::query()->find($id);

        if ($categoria === null) {
            abort(404, 'Categoría no encontrada');
        }

        return $categoria;
    }
}
