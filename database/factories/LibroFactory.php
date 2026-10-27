<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Libro>
 */
class LibroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ejemplaresTotales = $this->faker->numberBetween(1, 10);

        return [
            'isbn' => $this->faker->unique()->numerify('#############'),
            'titulo' => ucwords($this->faker->unique()->words(3, true)),
            'autor' => $this->faker->name(),
            'editorial' => $this->faker->company(),
            'anio_publicacion' => $this->faker->numberBetween(1900, (int) now()->year),
            'ejemplares_totales' => $ejemplaresTotales,
            'ejemplares_disponibles' => $ejemplaresTotales,
            'categoria_id' => Categoria::factory(),
            'portada' => null,
        ];
    }
}
