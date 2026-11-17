<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadosCargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_region_de_resultados_anuncia_el_estado_de_carga(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/catalogo')
            ->assertOk()
            ->assertSee('id="estado-carga"', false)
            ->assertSee('role="status"', false);
    }

    public function test_el_script_de_busqueda_escribe_cargando_al_enviar_el_criterio(): void
    {
        $script = file_get_contents(resource_path('js/busqueda-catalogo.js'));

        $this->assertStringContainsString("getElementById('estado-carga')", $script);
        $this->assertStringContainsString('Cargando…', $script);
    }

    public function test_el_estado_de_carga_se_incluye_en_la_pagina_con_texto_en_espanol(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/catalogo')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div id="estado-carga"[^>]*role="status"[^>]*><\/div>/', $html, 'Sin JavaScript queda vacío y sin estado intermedio');
    }
}
