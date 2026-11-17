<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccionPrincipalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_listado_muestra_una_accion_principal_visible(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/catalogo')->assertOk()->assertSee('Registrar préstamo');
        $this->get('/libros')->assertOk()->assertSee('Añadir libro');
        $this->get('/categorias')->assertOk()->assertSee('Nueva categoría');
        $this->get('/prestamos')->assertOk()->assertSee('Registrar préstamo');
    }

    public function test_el_registro_de_prestamos_es_alcanzable_desde_el_listado(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/prestamos')
            ->assertOk()
            ->assertSee('href="'.route('prestamos.create').'"', false);
    }

    public function test_el_home_con_sesion_ofrece_el_registro_de_prestamos(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('prestamos.create').'"', false)
            ->assertDontSee('Conexión a la base de datos verificada');
    }

    public function test_el_home_sin_sesion_conduce_al_login_sin_mostrar_estadisticas(): void
    {
        $this->sinSesion()->get('/')
            ->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('Libros registrados');
    }
}
