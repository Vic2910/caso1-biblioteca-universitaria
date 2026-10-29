<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoriaStoreRequest extends FormRequest
{
    /**
     * La regla unique es la primera capa de protección contra categorías duplicadas
     * (D-022). Sin ella, dos altas simultáneas podrían pasar la validación y ambas
     * filas llegarían a la base de datos. El índice compuesto del modelo de datos es
     * la segunda capa si esta validación se evade, y ninguna depende de la otra.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias', 'nombre')],
        ];
    }

    /**
     * Quita los espacios antes de validar para que la regla unique compare el valor
     * real del campo. Sin esto, «Matemáticas » parecería un nombre distinto, pasaría
     * la validación y se almacenaría con un espacio final que nadie ve pero que rompe
     * la unicidad en la base de datos. La validación rechaza el duplicado; el modelo
     * vuelve a recortar al persistir como defensa final.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El campo Nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
        ];
    }
}
