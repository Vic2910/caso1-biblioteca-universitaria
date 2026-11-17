<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * V-002/V-003 y SC-006: credenciales válidas inician sesión; los fallos
 * repetidos muestran siempre el mismo mensaje genérico, sin bloqueo ni espera.
 */
class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected bool $sesionPorDefecto = false;

    private const CLAVE = 'ClaveLocal123!';

    private function usuario(): User
    {
        return User::factory()->create([
            'email' => 'estudiante@ejemplo.local',
            'password' => Hash::make(self::CLAVE),
        ]);
    }

    public function test_las_credenciales_validas_redirigen_al_catalogo_y_autentican(): void
    {
        $usuario = $this->usuario();

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => self::CLAVE,
        ])->assertRedirect(route('catalogo.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_cinco_fallos_consecutivos_muestran_el_mismo_mensaje_sin_bloquear(): void
    {
        $usuario = $this->usuario();

        foreach (range(1, 5) as $intento) {
            $respuesta = $this->from(route('login'))->post('/login', [
                'email' => $intento % 2 === 0 ? 'no-existe@ejemplo.local' : $usuario->email,
                'password' => 'clave-erronea',
            ]);

            $respuesta->assertRedirect(route('login'));
            $respuesta->assertSessionHasErrors([
                'credenciales' => 'Correo o contraseña incorrectos.',
            ]);
            $this->assertGuest();
        }

        $this->get(route('login'))->assertOk();
    }

    public function test_el_correo_se_conserva_tras_un_fallo_de_credenciales(): void
    {
        $usuario = $this->usuario();

        $this->from(route('login'))->post('/login', [
            'email' => $usuario->email,
            'password' => 'clave-erronea',
        ])->assertSessionHasErrors('credenciales');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee($usuario->email, false);
    }

    public function test_un_login_valido_funciona_inmediatamente_despues_de_varios_fallos(): void
    {
        $usuario = $this->usuario();

        foreach (range(1, 3) as $intento) {
            $this->post('/login', [
                'email' => $usuario->email,
                'password' => 'clave-erronea',
            ])->assertSessionHasErrors('credenciales');
        }

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => self::CLAVE,
        ])->assertRedirect(route('catalogo.index'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_cierre_de_sesion_devuelve_al_login_y_protege_el_catalogo(): void
    {
        $usuario = $this->usuario();
        $this->actingAs($usuario);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->get(route('catalogo.index'))->assertRedirect(route('login'));
    }

    public function test_los_campos_vacios_se_rechazan_en_espanol_junto_al_campo(): void
    {
        $this->from(route('login'))->post('/login', [])
            ->assertSessionHasErrors([
                'email' => 'El campo Correo es obligatorio.',
                'password' => 'El campo Contraseña es obligatorio.',
            ]);
    }
}
