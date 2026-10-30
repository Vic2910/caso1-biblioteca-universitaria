<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Libro;
use App\Services\Prestamos\PrestamoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Reglas del formulario de registro de préstamo (specs/004-gestion-prestamos,
 * D-021, FR-003): campos con mensajes en español junto al campo y reglas de
 * negocio delegadas en `PrestamoService` (D-019) para que el rechazo aparezca
 * en el campo correcto sin duplicar lógica (R1 disponibilidad sobre
 * `libro_id`, R2 límite de tres sobre `carnet_estudiante`). El carné se
 * normaliza con `trim()` exclusivo antes de validar (D-023).
 */
class RegistrarPrestamoRequest extends FormRequest
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
            'libro_id' => ['required', 'integer', 'exists:libros,id'],
            'nombre_estudiante' => ['required', 'string', 'min:2', 'max:255'],
            'carnet_estudiante' => ['required', 'string', 'max:50'],
            'fecha_prestamo' => ['required', 'date'],
            'fecha_devolucion_prevista' => ['required', 'date', 'after:fecha_prestamo'],
        ];
    }

    /**
     * El carné se almacena y compara sin espacios sobrantes (D-023).
     */
    public function prepareForValidation(): void
    {
        if (is_string($this->input('carnet_estudiante'))) {
            $this->merge([
                'carnet_estudiante' => trim($this->string('carnet_estudiante')->toString()),
            ]);
        }
    }

    /**
     * Reglas de negocio que dependen de la base de datos, delegadas en el
     * servicio central (D-019, D-021, D-026). Solo se evalúan cuando el campo
     * superó sus reglas básicas, para no enmascarar el error de campo.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var PrestamoService $servicio */
            $servicio = $this->container->make(PrestamoService::class);
            $datos = $validator->getData();

            if (! $validator->errors()->has('libro_id') && isset($datos['libro_id'])) {
                $libro = Libro::find($datos['libro_id']);

                if ($libro !== null && ! $servicio->libroDisponible($libro)) {
                    $validator->errors()->add('libro_id', 'No hay ejemplares disponibles de este libro.');
                }
            }

            if (! $validator->errors()->has('carnet_estudiante') && isset($datos['carnet_estudiante'])) {
                if ($servicio->prestamosNoDevueltos((string) $datos['carnet_estudiante']) >= 3) {
                    $validator->errors()->add('carnet_estudiante', 'El estudiante ya tiene el máximo de tres préstamos sin devolver.');
                }
            }
        });
    }

    /**
     * Mensajes en español literales del contrato user-messages.md §2.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libro_id.required' => 'El campo libro es obligatorio.',
            'libro_id.integer' => 'El libro seleccionado no existe.',
            'libro_id.exists' => 'El libro seleccionado no existe.',
            'nombre_estudiante.required' => 'El campo nombre del estudiante es obligatorio.',
            'nombre_estudiante.min' => 'El nombre del estudiante debe tener al menos 2 caracteres.',
            'nombre_estudiante.max' => 'El nombre del estudiante no puede superar los 255 caracteres.',
            'carnet_estudiante.required' => 'El campo carné es obligatorio.',
            'carnet_estudiante.max' => 'El carné no puede superar los 50 caracteres.',
            'fecha_prestamo.required' => 'La fecha de préstamo es obligatoria.',
            'fecha_prestamo.date' => 'La fecha de préstamo no es válida.',
            'fecha_devolucion_prevista.required' => 'La devolución prevista es obligatoria.',
            'fecha_devolucion_prevista.date' => 'La devolución prevista no es válida.',
            'fecha_devolucion_prevista.after' => 'La devolución prevista debe ser posterior a la fecha de préstamo.',
        ];
    }

    /**
     * Nombres de campo en español junto a los mensajes (contrato user-messages.md §2).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'libro_id' => 'libro',
            'nombre_estudiante' => 'nombre del estudiante',
            'carnet_estudiante' => 'carné',
            'fecha_prestamo' => 'fecha de préstamo',
            'fecha_devolucion_prevista' => 'devolución prevista',
        ];
    }
}
