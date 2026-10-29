<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoriaUpdateRequest extends FormRequest
{
    /**
     * Ignora el propio registro para que la categoría pueda conservar su nombre, y
     * valida contra todos los demás. Si el id de la ruta no corresponde a ninguna
     * categoría, la edición no tiene sentido y se responde 404.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        $categoria = $this->resolveCategoria();

        abort_if($categoria === null, 404, 'Categoría no encontrada');

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categorias', 'nombre')->ignore($categoria->id),
            ],
        ];
    }

    /**
     * Quita los espacios antes de validar para que la regla unique compare el valor
     * real del campo. Sin esto, «Matemática » parecería un nombre distinto al
     * existente, pasaría la validación y se almacenaría con un espacio final que
     * nadie ve pero que rompe la unicidad en la base de datos. La validación rechaza
     * el duplicado; el modelo vuelve a recortar al persistir como defensa final.
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

    private function resolveCategoria(): ?Categoria
    {
        $categoria = $this->route('categoria');

        if ($categoria instanceof Categoria) {
            return $categoria;
        }

        if (is_numeric($categoria)) {
            return Categoria::find($categoria);
        }

        return null;
    }
}
