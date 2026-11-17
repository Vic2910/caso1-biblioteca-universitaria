<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesionExtenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_layout_publica_la_caducidad_de_la_sesion_con_sesion_iniciada(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/catalogo')
            ->assertOk()
            ->assertSee('data-sesion-expira="', false)
            ->assertSee('id="aviso-sesion"', false);
    }

    public function test_sin_sesion_no_hay_aviso_ni_caducidad_publicada(): void
    {
        $this->sinSesion()->get('/')
            ->assertOk()
            ->assertDontSee('data-sesion-expira', false)
            ->assertDontSee('id="aviso-sesion"', false);
    }

    public function test_extender_la_sesion_conserva_los_datos_y_responde_con_exito(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->withSession(['borrador' => 'datos-introducidos'])
            ->from('/catalogo')
            ->post(route('sesion.extender'))
            ->assertRedirect('/catalogo')
            ->assertSessionHas('success', 'Sesión extendida.')
            ->assertSessionHas('borrador', 'datos-introducidos');
    }

    public function test_extender_exige_sesion_iniciada(): void
    {
        $this->sinSesion()->post(route('sesion.extender'))->assertRedirect(route('login'));
    }
}
