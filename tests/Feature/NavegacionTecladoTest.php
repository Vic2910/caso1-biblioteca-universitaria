<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionTecladoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_enlace_para_saltar_al_contenido_es_el_primer_elemento_enfocable(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/catalogo')->assertOk()->getContent();

        $posicionSalto = strpos($html, 'class="enlace-saltar"');
        $posicionNavegacion = strpos($html, '<nav');

        $this->assertNotFalse($posicionSalto);
        $this->assertLessThan($posicionNavegacion, $posicionSalto, 'El enlace para saltar va antes de la navegación');
        $this->assertStringContainsString('<main id="contenido"', $html);
    }

    public function test_los_desplegables_exponen_su_estado_a_la_tecnologia_de_apoyo(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/catalogo')->assertOk()->getContent();

        $this->assertStringContainsString('<summary', $html, 'Los disparadores son summary, activables con Enter y Espacio');
        $this->assertStringContainsString('data-menu', $html, 'La mejora de JavaScript localiza los menús por este atributo');
    }

    public function test_no_hay_tabindex_positivos_que_alteren_el_orden_de_foco(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/catalogo')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/tabindex="[1-9]\d*"/', $html, 'El orden de foco coincide con el orden visual (2.4.3)');
    }

    public function test_la_pagina_activa_se_marca_con_aria_current(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/libros')->assertOk()->assertSee('aria-current="page"', false);
    }
}
