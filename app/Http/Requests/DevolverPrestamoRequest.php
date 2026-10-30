<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Prestamo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Reglas del formulario de devolución (specs/004-gestion-prestamos, D-021,
 * FR-006): la fecha real es obligatoria y no puede ser anterior a la fecha
 * de préstamo del registro en curso (R4). La comparación cruza la entrada y
 * un registro existente, por lo que se evalúa en `withValidator()` contra el
 * modelo (D-021). Mensajes en español del contrato user-messages.md §3.
 *
 * [CONCEPTO] Reglas de préstamo visibles en este formulario:
 *  - La fecha de devolución real es obligatoria y nunca anterior a la fecha
 *    de préstamo; un préstamo vencido también puede devolverse.
 *  - Un préstamo ya devuelto no admite una segunda devolución (M2); la
 *    disponibilidad del libro se suma exactamente una vez.
 */
class DevolverPrestamoRequest extends FormRequest
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
            'fecha_devolucion_real' => ['required', 'date'],
        ];
    }

    /**
     * R4: la fecha real no puede ser anterior a la fecha de préstamo del
     * préstamo en curso (válido también para préstamos `vencido`).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('fecha_devolucion_real')) {
                return;
            }

            $prestamo = Prestamo::find($this->route('id'));

            if ($prestamo === null) {
                return;
            }

            $fechaReal = Carbon::parse($this->input('fecha_devolucion_real'))->startOfDay();

            if ($fechaReal->lt($prestamo->fecha_prestamo->startOfDay())) {
                $validator->errors()->add(
                    'fecha_devolucion_real',
                    'La fecha de devolución real no puede ser anterior a la fecha de préstamo.'
                );
            }
        });
    }

    /**
     * Mensajes en español literales del contrato user-messages.md §3.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_devolucion_real.required' => 'La fecha de devolución real es obligatoria.',
            'fecha_devolucion_real.date' => 'La fecha de devolución real no es válida.',
        ];
    }

    /**
     * Nombres de campo en español junto a los mensajes (contrato user-messages.md §3).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_devolucion_real' => 'fecha de devolución real',
        ];
    }
}
