<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Libro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Entrega y gestión de las portadas de libros (specs/005-gestion-portadas,
 * D-018/D-023): las imágenes se sirven por ruta controlada —nunca por el
 * enlace simbólico `/storage`— exigiendo sesión antes de tocar la base de
 * datos o el disco, de modo que un visitante no infiere qué portadas
 * existen (FR-007, SC-006).
 */
class PortadaController extends Controller
{
    /**
     * Sirve la imagen de portada de un libro con sesión iniciada.
     *
     * Orden fijo (D-023): comprobación de sesión → libro → referencia en la
     * base de datos → archivo en disco. Sin sesión nunca se revela la
     * existencia de la portada ni se envían sus bytes (FR-007).
     */
    public function show(string $libro): Response
    {
        if (! auth()->check()) {
            return response()->view('errors.401', [], 401);
        }

        $modelo = $this->resolverLibro($libro);

        if ($modelo->portada === null) {
            abort(404, 'Portada no encontrada');
        }

        $disco = Storage::disk('public');

        if (! $disco->exists($modelo->portada)) {
            abort(404, 'Portada no encontrada');
        }

        return $disco->response($modelo->portada, null, [
            'Content-Type' => $this->tipoMime($modelo->portada),
        ]);
    }

    /**
     * Elimina la portada del libro (specs/005, FR-005; transición T3 de
     * data-model.md §Ciclo de vida).
     *
     * Orden fijo: libro → referencia en la base de datos → disco. Si el
     * borrado del archivo falla, archivo y referencia quedan intactos y se
     * responde 302 a la edición con el mensaje literal de
     * `user-messages.md` §2 junto al campo `portada` (D-025,
     * contracts/http-endpoints.md §2). Solo existe la ruta DELETE: la
     * confirmación es nativa y previa al envío (SC-004).
     */
    public function destroy(string $libro): RedirectResponse
    {
        $modelo = $this->resolverLibro($libro);

        if ($modelo->portada === null) {
            abort(404, 'Portada no encontrada');
        }

        try {
            $eliminado = Storage::disk('public')->delete($modelo->portada);
        } catch (Throwable) {
            $eliminado = false;
        }

        if (! $eliminado) {
            return $this->errorPortada($modelo);
        }

        $modelo->portada = null;
        $modelo->save();
        $modelo->reconciliarPortada();

        return redirect()
            ->route('libros.edit', $modelo)
            ->with('exito', 'Portada eliminada correctamente.');
    }

    /**
     * Tipo MIME derivado de la extensión registrada: los tres formatos
     * admitidos por la validación (JPG, PNG y WebP, D-019) son los únicos
     * que la aplicación almacena.
     */
    private function tipoMime(string $ruta): string
    {
        return match (strtolower(pathinfo($ruta, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Libro por su id numérico o 404 en español con la vista compartida
     * de `002` (mecanismo D-013).
     */
    private function resolverLibro(string $id): Libro
    {
        $libro = Libro::find($id);

        if ($libro === null) {
            abort(404, 'Libro no encontrado');
        }

        return $libro;
    }

    /**
     * 302 de vuelta al formulario de edición con el mensaje literal de
     * `user-messages.md` §2 junto al campo `portada`: sin cambios en la
     * portada ni en el registro del libro (FR-006, D-025).
     */
    private function errorPortada(Libro $libro): RedirectResponse
    {
        return redirect()
            ->route('libros.edit', $libro)
            ->withErrors(['portada' => 'No se pudo guardar la imagen. Inténtelo de nuevo.']);
    }
}
