<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LibroStoreRequest;
use App\Http\Requests\LibroUpdateRequest;
use App\Models\Categoria;
use App\Models\Libro;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD de libros (specs/003-gestion-libros, D-018): alta y edición sobre las
 * vistas del catálogo de `002`. Toda escritura pasa por Form Requests y
 * `validated()` (Principio II); la disponibilidad la calcula el modelo
 * (D-023) y la portada solo se toca cuando llega un archivo (D-021).
 */
class LibroController extends Controller
{
    /**
     * Formulario de alta de libro con el selector de categorías.
     */
    public function create(): View
    {
        return view('libros.create', ['categorias' => $this->categorias()]);
    }

    /**
     * Registra el libro validado y redirige a su ficha con mensaje de éxito.
     */
    public function store(LibroStoreRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        if ($request->hasFile('portada')) {
            $datos['portada'] = $request->file('portada')->store('portadas', 'public');
        }

        $libro = Libro::create($datos);

        return redirect()
            ->route('catalogo.show', $libro)
            ->with('success', 'Libro registrado correctamente.');
    }

    /**
     * Formulario de edición precargado; libro inexistente → 404 en español.
     */
    public function edit(string $libro): View
    {
        return view('libros.edit', [
            'libro' => $this->resolverLibro($libro),
            'categorias' => $this->categorias(),
        ]);
    }

    /**
     * Actualiza el libro validado; si no se envía archivo, la portada
     * existente queda intacta (D-021; el reemplazo es de `005`).
     */
    public function update(LibroUpdateRequest $request, string $libro): RedirectResponse
    {
        $modelo = $this->resolverLibro($libro);
        $datos = $request->validated();

        if ($request->hasFile('portada')) {
            $datos['portada'] = $request->file('portada')->store('portadas', 'public');
        } else {
            unset($datos['portada']);
        }

        $modelo->fill($datos)->save();

        return redirect()
            ->route('catalogo.show', $modelo)
            ->with('success', 'Libro actualizado correctamente.');
    }

    /**
     * Categorías ordenadas para los selectores de los formularios.
     *
     * @return Collection<int, Categoria>
     */
    private function categorias(): Collection
    {
        return Categoria::query()->orderBy('nombre')->get();
    }

    /**
     * Resuelve el libro por su id numérico o responde 404 en español
     * con la vista compartida de `002` (mecanismo D-013).
     */
    private function resolverLibro(string $id): Libro
    {
        $libro = Libro::find($id);

        if ($libro === null) {
            abort(404, 'Libro no encontrado');
        }

        return $libro;
    }
}
