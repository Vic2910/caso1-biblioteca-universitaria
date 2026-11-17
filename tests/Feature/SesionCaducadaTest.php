<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * V-004 y SC-005: una sesión inactiva durante más de dos horas deja de ser
 * válida antes de procesar cualquier operación (008, FR-009, D-038).
 */
class SesionCaducadaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $sesionPorDefecto = false;

    private const CLAVE = 'ClaveLocal123!';

    public function test_una_sesion_caducada_redirige_al_login_y_no_ejecuta_la_operacion(): void
    {
        $usuario = User::factory()->create([
            'email' => 'estudiante@ejemplo.local',
            'password' => Hash::make(self::CLAVE),
        ]);

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => self::CLAVE,
        ])->assertRedirect(route('catalogo.index'));

        $this->travel(121)->minutes();

        $this->get(route('catalogo.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Su sesión expiró. Inicie sesión de nuevo.');

        $this->post(route('categorias.store'), ['nombre' => 'Categoría caducada'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('categorias', 0);
    }

    public function test_el_login_muestra_el_mensaje_de_sesion_caducada(): void
    {
        $usuario = User::factory()->create([
            'email' => 'estudiante@ejemplo.local',
            'password' => Hash::make(self::CLAVE),
        ]);

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => self::CLAVE,
        ])->assertRedirect(route('catalogo.index'));

        $this->travel(121)->minutes();

        $this->get(route('panel.index'))->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Su sesión expiró. Inicie sesión de nuevo.');
    }

    public function test_una_sesion_reciente_sigue_procesando_las_operaciones(): void
    {
        Categoria::factory()->create(['nombre' => 'Categoría previa']);

        $usuario = User::factory()->create([
            'email' => 'estudiante@ejemplo.local',
            'password' => Hash::make(self::CLAVE),
        ]);

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => self::CLAVE,
        ])->assertRedirect(route('catalogo.index'));

        $this->travel(119)->minutes();

        $this->get(route('catalogo.index'))->assertOk();
    }
}
