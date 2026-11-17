<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Middleware de CSRF sin la excepción que Laravel aplica en las pruebas:
 * `runningUnitTests()` se fuerza a false para verificar el token de verdad.
 */
class CsrfVerificacionActiva extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

/**
 * V-006 y SC-003: ningún formulario puede guardar datos sin un token CSRF
 * válido; el fallo vuelve al origen con el mensaje literal y `old()` (FR-005).
 */
class CsrfProteccionTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE_CSRF = 'Su sesión expiró o el formulario ya no es válido. Actualice la página e inténtelo de nuevo.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(PreventRequestForgery::class, CsrfVerificacionActiva::class);
    }

    public function test_un_formulario_sin_token_no_guarda_y_muestra_el_mensaje_exacto(): void
    {
        $respuesta = $this->from(route('categorias.create'))->post('/categorias', [
            'nombre' => 'Categoría sin token',
        ]);

        $respuesta->assertRedirect(route('categorias.create'));
        $respuesta->assertSessionHasErrors(['csrf' => self::MENSAJE_CSRF]);

        $this->assertDatabaseCount('categorias', 0);

        $this->get(route('categorias.create'))
            ->assertOk()
            ->assertSee(self::MENSAJE_CSRF);
    }

    public function test_un_token_manipulado_no_guarda_y_conserva_el_valor_introducido(): void
    {
        $this->get(route('categorias.create'))->assertOk();

        $respuesta = $this->from(route('categorias.create'))->post('/categorias', [
            '_token' => 'token-manipulado',
            'nombre' => 'Valor conservado',
        ]);

        $respuesta->assertRedirect(route('categorias.create'));
        $respuesta->assertSessionHasErrors(['csrf' => self::MENSAJE_CSRF]);
        $respuesta->assertSessionHasInput('nombre', 'Valor conservado');

        $this->assertDatabaseCount('categorias', 0);

        $this->get(route('categorias.create'))
            ->assertOk()
            ->assertSee('Valor conservado');
    }

    public function test_el_middleware_de_csrf_no_tiene_rutas_excluidas(): void
    {
        $middleware = $this->app->make(PreventRequestForgery::class);

        $this->assertSame([], $middleware->getExcludedPaths());
        $this->assertInstanceOf(CsrfVerificacionActiva::class, $middleware);
    }

    public function test_las_peticiones_de_lectura_siguen_funcionando_con_la_verificacion_activa(): void
    {
        $this->get(route('categorias.index'))->assertOk();
        $this->get(route('catalogo.index'))->assertOk();
    }
}
