<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el escenario V-011 de specs/004-gestion-prestamos/quickstart.md
 * (D-025, FR-002, SC-003, data-model.md §Transiciones de estado): el estado
 * `vencido` se calcula con devolución prevista estrictamente anterior a hoy
 * (vencido desde el día siguiente) y se evalúa sin intervención manual al
 * registrar, al devolver y al listar; los `devuelto` son estado final y
 * jamás cambian.
 */
class PrestamoVencimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_prestamo_con_devolucion_prevista_hoy_sigue_activo(): void
    {
        $prestamo = Prestamo::factory()->activo()->create([
            'libro_id' => Libro::factory()->create()->id,
            'fecha_devolucion_prevista' => today()->format('Y-m-d'),
        ]);

        $respuesta = $this->get('/prestamos');

        $respuesta->assertOk();
        $this->assertSame(Prestamo::ESTADO_ACTIVO, $prestamo->fresh()->estado, 'Con prevista = hoy sigue activo (vencido desde el día siguiente)');
        $respuesta->assertSee('>Activo<', false);
        $respuesta->assertDontSee('>Vencido<', false);
    }

    public function test_un_prestamo_con_devolucion_prevista_ayer_pasa_a_vencido_al_listar_sin_intervencion_manual(): void
    {
        $prestamo = Prestamo::factory()->create([
            'libro_id' => Libro::factory()->create()->id,
            'fecha_prestamo' => now()->subDays(10)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->subDay()->format('Y-m-d'),
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_ACTIVO,
        ]);

        $respuesta = $this->get('/prestamos');

        $respuesta->assertOk();
        $this->assertSame(Prestamo::ESTADO_VENCIDO, $prestamo->fresh()->estado, 'Al listar se evalúa el vencimiento');
        $respuesta->assertSee('>Vencido<', false);
    }

    public function test_el_estado_se_evalua_al_registrar(): void
    {
        $libro = Libro::factory()->create();
        $porVencer = Prestamo::factory()->create([
            'libro_id' => $libro->id,
            'fecha_prestamo' => now()->subDays(10)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->subDay()->format('Y-m-d'),
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_ACTIVO,
        ]);

        $this->post('/prestamos', [
            'libro_id' => $libro->id,
            'nombre_estudiante' => 'Ana Martínez López',
            'carnet_estudiante' => '202400123',
            'fecha_prestamo' => today()->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->addDays(14)->format('Y-m-d'),
        ])->assertRedirect(route('prestamos.index'));

        $this->assertSame(Prestamo::ESTADO_VENCIDO, $porVencer->fresh()->estado, 'Al registrar se evalúa el vencimiento');
    }

    public function test_el_estado_se_evalua_al_devolver(): void
    {
        $libro = Libro::factory()->create();
        $porDevolver = Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);
        $porVencer = Prestamo::factory()->create([
            'libro_id' => $libro->id,
            'fecha_prestamo' => now()->subDays(10)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->subDay()->format('Y-m-d'),
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_ACTIVO,
        ]);

        $this->post("/prestamos/{$porDevolver->id}/devolver", [
            'fecha_devolucion_real' => today()->format('Y-m-d'),
        ])->assertRedirect(route('prestamos.index'));

        $this->assertSame(Prestamo::ESTADO_DEVUELTO, $porDevolver->fresh()->estado);
        $this->assertSame(Prestamo::ESTADO_VENCIDO, $porVencer->fresh()->estado, 'Al devolver se evalúa el vencimiento');
    }

    public function test_los_prestamos_devueltos_nunca_cambian_de_estado(): void
    {
        $libro = Libro::factory()->create();
        $devuelto = Prestamo::factory()->devuelto()->create([
            'libro_id' => $libro->id,
            'fecha_devolucion_prevista' => now()->subDays(16)->format('Y-m-d'),
            'fecha_devolucion_real' => now()->subDays(17)->format('Y-m-d'),
        ]);
        $porDevolver = Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);

        $this->get('/prestamos')->assertOk();

        $this->post('/prestamos', [
            'libro_id' => $libro->id,
            'nombre_estudiante' => 'Luis Gómez Saez',
            'carnet_estudiante' => '202400999',
            'fecha_prestamo' => today()->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->addDays(14)->format('Y-m-d'),
        ])->assertRedirect(route('prestamos.index'));

        $this->post("/prestamos/{$porDevolver->id}/devolver", [
            'fecha_devolucion_real' => today()->format('Y-m-d'),
        ])->assertRedirect(route('prestamos.index'));

        $fresco = $devuelto->fresh();
        $this->assertSame(Prestamo::ESTADO_DEVUELTO, $fresco->estado, 'Los devueltos jamás cambian de estado');
        $this->assertNotNull($fresco->fecha_devolucion_real, 'Los devueltos conservan su fecha de devolución real');
    }
}
