<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas de alta de libro (specs/003-gestion-libros, D-019, FR-003/006):
 * ISBN único de exactamente 13 dígitos, año 1900–año actual, campos
 * obligatorios y longitudes, y portada opcional JPG/PNG/WebP ≤ 2 MB.
 * `ejemplares_disponibles` no se captura ni se valida: lo calcula el
 * servidor (clarificación Q1, D-023).
 */
class LibroStoreRequest extends FormRequest
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
        return [
            'isbn' => ['required', 'string', 'regex:/^[0-9]{13}$/', 'unique:libros,isbn'],
            'titulo' => ['required', 'string', 'min:2', 'max:255'],
            'autor' => ['required', 'string', 'min:2', 'max:255'],
            'editorial' => ['required', 'string', 'max:255'],
            'anio_publicacion' => ['required', 'integer', 'min:1900', 'max:'.now()->year],
            'ejemplares_totales' => ['required', 'integer', 'min:1'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'portada' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Mensajes en español cuyo texto no puede derivarse de la traducción
     * genérica (contrato user-messages.md §1; el límite superior del año se
     * calcula en el momento de la petición).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajeAnio = 'El año de publicación debe estar entre 1900 y '.now()->year.'.';

        return [
            'anio_publicacion.min' => $mensajeAnio,
            'anio_publicacion.max' => $mensajeAnio,
        ];
    }
}
