<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Libro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas de edición de libro (specs/003-gestion-libros, D-019/D-023, FR-007):
 * idénticas a las de alta salvo el ISBN (ignora el propio libro en la
 * unicidad) y la cota inferior dinámica de `ejemplares_totales`, que nunca
 * puede quedar por debajo de los préstamos activos del libro.
 */
class LibroUpdateRequest extends FormRequest
{
    /**
     * Determina si el usuario puede realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string|object>>
     */
    public function rules(): array
    {
        $libro = $this->libro();

        if ($libro === null) {
            abort(404, 'Libro no encontrado');
        }

        return [
            'isbn' => ['required', 'string', 'regex:/^[0-9]{13}$/', Rule::unique('libros', 'isbn')->ignore($libro->id)],
            'titulo' => ['required', 'string', 'min:2', 'max:255'],
            'autor' => ['required', 'string', 'min:2', 'max:255'],
            'editorial' => ['required', 'string', 'max:255'],
            'anio_publicacion' => ['required', 'integer', 'min:1900', 'max:'.now()->year],
            'ejemplares_totales' => ['required', 'integer', 'min:'.max(1, $libro->prestamosActivos())],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'portada' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Mensajes en español cuyo texto no puede derivarse de la traducción
     * genérica (contrato user-messages.md §1): el límite superior del año y,
     * cuando hay préstamos activos, la cota inferior del total de ejemplares.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajeAnio = 'El año de publicación debe estar entre 1900 y '.now()->year.'.';

        $mensajes = [
            'anio_publicacion.min' => $mensajeAnio,
            'anio_publicacion.max' => $mensajeAnio,
        ];

        $prestamosActivos = $this->libro()->prestamosActivos();

        if ($prestamosActivos > 0) {
            $mensajes['ejemplares_totales.min'] = 'El número de ejemplares no puede ser inferior a los préstamos activos ('.$prestamosActivos.').';
        }

        return $mensajes;
    }

    /**
     * Libro en edición, resuelto desde el parámetro de ruta (id o modelo).
     */
    private function libro(): ?Libro
    {
        $libro = $this->route('libro');

        if ($libro instanceof Libro) {
            return $libro;
        }

        return Libro::find($libro);
    }
}
