<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Prestamo>
 */
class PrestamoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaPrestamo = $this->faker->dateTimeBetween('-30 days', '-10 days');

        return [
            'libro_id' => Libro::factory(),
            'nombre_estudiante' => $this->faker->name(),
            'carnet_estudiante' => $this->faker->unique()->numerify('########'),
            'fecha_prestamo' => $fechaPrestamo->format('Y-m-d'),
            'fecha_devolucion_prevista' => $fechaPrestamo->modify('+14 days')->format('Y-m-d'),
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_ACTIVO,
        ];
    }

    /**
     * Préstamo activo: sin devolución real y con devolución prevista futura.
     */
    public function activo(): static
    {
        return $this->state(fn (): array => [
            'fecha_prestamo' => now()->subDays(3)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->addDays(11)->format('Y-m-d'),
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_ACTIVO,
        ]);
    }

    /**
     * Préstamo devuelto: con fecha de devolución real registrada.
     */
    public function devuelto(): static
    {
        return $this->state(fn (): array => [
            'fecha_prestamo' => now()->subDays(30)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->subDays(16)->format('Y-m-d'),
            'fecha_devolucion_real' => now()->subDays(17)->format('Y-m-d'),
            'estado' => Prestamo::ESTADO_DEVUELTO,
        ]);
    }

    /**
     * Préstamo vencido: sin devolución real y con devolución prevista pasada.
     */
    public function vencido(): static
    {
        return $this->state(fn (): array => [
            'fecha_prestamo' => now()->subDays(40)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->subDays(26)->format('Y-m-d'),
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_VENCIDO,
        ]);
    }
}
