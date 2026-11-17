<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el escenario V-007 (página fuera de rango → 404)
 * de specs/002-consulta-catalogo/quickstart.md (FR-005, historia 1 criterio 5).
 */
class CatalogoErroresTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_paginas_no_numericas_o_fuera_de_rango_responden_404_con_vista_personalizada(): void
    {
        Libro::factory()->count(3)->create();

        foreach (['999', '0', 'abc', '-3', '1.5'] as $pagina) {
            $respuesta = $this->get('/catalogo?page='.$pagina);

            $respuesta->assertNotFound();
            $respuesta->assertSee('Página no válida');
            $respuesta->assertSee('Volver al catálogo');
            $respuesta->assertSee(route('catalogo.index'));
            $respuesta->assertSee('Biblioteca Universitaria', false);
        }
    }

    public function test_con_catalogo_vacio_solo_la_pagina_un_es_valida(): void
    {
        // Base migrada sin sembrar: total de libros = 0.
        $this->get('/catalogo')
            ->assertOk()
            ->assertSee('No hay libros registrados aún');

        $respuesta = $this->get('/catalogo?page=2');

        $respuesta->assertNotFound();
        $respuesta->assertSee('Página no válida');
        $respuesta->assertSee('Volver al catálogo');
    }

    public function test_la_pagina_un_es_valida_con_libros_y_respeta_la_ultima_pagina(): void
    {
        Libro::factory()->count(12)->create();

        $this->get('/catalogo')->assertOk();
        $this->get('/catalogo?page=2')->assertOk();

        $this->get('/catalogo?page=3')->assertNotFound();
    }

    public function test_el_parametro_page_vacio_equivale_a_la_pagina_uno(): void
    {
        Libro::factory()->create();

        $this->get('/catalogo?page=')->assertOk();
    }
}
