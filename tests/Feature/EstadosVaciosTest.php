<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V-009 y SC-008: ningún listado muestra un área vacía sin explicación; los
 * gestionables ofrecen además la acción para crear el primer registro (D-046).
 */
class EstadosVaciosTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_listados_gestionables_muestran_mensaje_y_accion(): void
    {
        $casos = [
            route('libros.index') => ['No hay registros', 'Añadir libro'],
            route('categorias.index') => ['No hay registros', 'Nueva categoría'],
            route('prestamos.index') => ['No hay registros', 'Registrar préstamo'],
        ];

        foreach ($casos as $url => [$mensaje, $accion]) {
            $respuesta = $this->get($url)->assertOk();

            $respuesta->assertSee($mensaje);
            $respuesta->assertSee($accion);
        }
    }

    public function test_el_catalogo_y_el_panel_conservan_sus_mensajes_propios(): void
    {
        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('No hay libros registrados aún');

        $this->get(route('panel.index'))
            ->assertOk()
            ->assertSee('No hay datos disponibles');
    }
}
