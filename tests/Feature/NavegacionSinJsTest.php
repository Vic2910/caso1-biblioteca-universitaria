<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionSinJsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_navegacion_se_resuelve_con_elementos_nativos_sin_javascript(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/catalogo')->assertOk()->getContent();

        $this->assertStringContainsString('<details class="menu-desplegable"', $html, 'El menú Gestión es un details nativo');
        $this->assertStringContainsString('<details class="menu-hamburguesa"', $html, 'La hamburguesa es un details nativo');
        $this->assertStringContainsString('<a class="enlace-saltar" href="#contenido">', $html, 'Enlace para saltar al contenido');
        $this->assertStringNotContainsString('onclick=', $html, 'Ningún control de navegación depende de manejadores inline');
    }

    public function test_el_menu_desplegable_agrupa_libros_categorias_y_prestamos(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/catalogo')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('libros.index').'"', $html);
        $this->assertStringContainsString('href="'.route('categorias.index').'"', $html);
        $this->assertStringContainsString('href="'.route('prestamos.index').'"', $html);
    }

    public function test_el_home_es_publico_y_no_redirige_al_login(): void
    {
        $this->sinSesion()->get('/')->assertOk()->assertSee('Iniciar sesión');
    }
}
