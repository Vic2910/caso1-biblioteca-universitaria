<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LibroStoreRequest;
use App\Http\Requests\LibroUpdateRequest;
use App\Models\Categoria;
use App\Models\Libro;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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
     * existente queda intacta (transición T4, FR-001). Con archivo nuevo
     * ejecuta la secuencia T1/T2 de specs/005-gestion-portadas (D-020):
     * guardar el archivo, persistir la referencia, borrar el anterior y
     * reconciliar el directorio (D-020/D-025).
     */
    public function update(LibroUpdateRequest $request, string $libro): RedirectResponse
    {
        $modelo = $this->resolverLibro($libro);
        $datos = $request->validated();
        unset($datos['portada']);

        if (! $request->hasFile('portada')) {
            $modelo->fill($datos)->save();

            return redirect()
                ->route('catalogo.show', $modelo)
                ->with('success', 'Libro actualizado correctamente.');
        }

        return $this->reemplazarPortada($request, $modelo, $datos);
    }

    /**
     * Elimina el libro con las tres capas de D-022 (specs/003-gestion-libros):
     * comprobación previa de préstamos, respaldo `RESTRICT` de la base de
     * datos ante carreras, y confirmación previa en el cliente. Solo existe
     * la ruta DELETE: GET nunca elimina (FR-005, SC-003).
     */
    public function destroy(string $libro): RedirectResponse
    {
        $modelo = $this->resolverLibro($libro);

        if ($modelo->prestamos()->exists()) {
            return redirect()
                ->route('catalogo.index')
                ->with('error', 'El libro tiene préstamos registrados y no puede eliminarse.');
        }

        try {
            $modelo->delete();
        } catch (QueryException) {
            return redirect()
                ->route('catalogo.index')
                ->with('error', 'No se pudo eliminar el registro. Inténtelo de nuevo.');
        }

        return redirect()
            ->route('catalogo.index')
            ->with('success', 'Libro eliminado correctamente.');
    }

    /**
     * Transiciones T1/T2 de specs/005-gestion-portadas (D-020): guarda
     * primero el archivo nuevo en `portadas/{id}/`, persiste la referencia
     * en la base de datos, borra el archivo anterior y reconcilia el
     * directorio para que nunca queden huérfanos (SC-005).
     *
     * Un fallo de disco o de la base de datos responde con el mensaje de
     * contrato junto al campo `portada`, conservando la portada previa y el
     * registro intactos (FR-006, D-025); si la base de datos falla tras
     * guardar el archivo, este se elimina como compensación.
     *
     * @param  array<string, mixed>  $datos
     */
    private function reemplazarPortada(LibroUpdateRequest $request, Libro $modelo, array $datos): RedirectResponse
    {
        $archivo = $request->file('portada');
        $disco = Storage::disk('public');
        $rutaAnterior = $modelo->portada;

        try {
            $extension = $archivo->guessExtension() ?? 'jpg';
            $ruta = $disco->putFileAs("portadas/{$modelo->id}", $archivo, Str::uuid()->toString().'.'.$extension);
        } catch (Throwable) {
            $ruta = false;
        }

        if ($ruta === false) {
            return $this->errorPortada($modelo);
        }

        try {
            $modelo->fill($datos);
            $modelo->portada = $ruta;
            $modelo->save();
        } catch (Throwable) {
            try {
                $disco->delete($ruta);
            } catch (Throwable) {
                // Compensación tolerante: si fallara, el archivo lo retira
                // la reconciliación futura del directorio (D-020).
            }

            return $this->errorPortada($modelo);
        }

        if ($rutaAnterior !== null && $rutaAnterior !== $ruta) {
            try {
                $disco->delete($rutaAnterior);
            } catch (Throwable) {
                // Lo retira la reconciliación del paso siguiente (D-020).
            }
        }

        $modelo->reconciliarPortada();

        return redirect()
            ->route('libros.edit', $modelo)
            ->with('exito', 'Portada actualizada correctamente.');
    }

    /**
     * 302 de vuelta al formulario de edición con el mensaje literal de
     * `user-messages.md` §2 junto al campo `portada` y los valores del
     * formulario conservados con `old()` (sin rutas ni trazas, D-025).
     */
    private function errorPortada(Libro $libro): RedirectResponse
    {
        return redirect()
            ->route('libros.edit', $libro)
            ->withInput(request()->input())
            ->withErrors(['portada' => 'No se pudo guardar la imagen. Inténtelo de nuevo.']);
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
