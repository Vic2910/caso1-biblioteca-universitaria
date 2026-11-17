<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprobaciones transversales de specs/003-gestion-libros/quickstart.md
 * (SC-001…SC-008): títulos con `<script>` escapados con `{{ }}`, y las
 * rutas de edición de libro y categoría inexistentes responden el 404
 * en español con el layout compartido (mecanismo D-013).
 */
class ComprobacionesTransversalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_titulo_con_script_se_escapa_en_listado_y_ficha(): void
    {
        $libro = Libro::factory()->create(['titulo' => '<script>alert(1)</script>']);

        $listado = $this->get('/catalogo')->assertOk()->getContent();
        $ficha = $this->get('/catalogo/'.$libro->id)->assertOk()->getContent();

        foreach ([$listado, $ficha] as $html) {
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'El título debe escaparse con {{ }} (Principio IV)');
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html, 'El título debe verse escapado, no eliminado');
        }
    }

    public function test_un_nombre_de_categoria_con_script_se_escapa_en_el_selector_de_libros(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => '<script>alert(1)</script>']);

        $html = $this->get(route('libros.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'El nombre de categoría debe escaparse con {{ }} (Principio IV)');
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_el_404_de_edicion_de_libro_usa_la_vista_espanola_con_layout_compartido(): void
    {
        $respuesta = $this->get('/libros/99999/editar');

        $respuesta->assertNotFound();
        $respuesta->assertSee('Libro no encontrado');
        $respuesta->assertSee('<h1>Biblioteca Universitaria</h1>', false);
        $respuesta->assertSee('<footer class="site-footer">', false);
    }

    public function test_el_404_de_edicion_de_categoria_usa_la_vista_espanola_con_layout_compartido(): void
    {
        $respuesta = $this->get('/categorias/99999/editar');

        $respuesta->assertNotFound();
        $respuesta->assertSee('Categoría no encontrada');
        $respuesta->assertSee('<h1>Biblioteca Universitaria</h1>', false);
        $respuesta->assertSee('<footer class="site-footer">', false);
    }

    public function test_el_alta_de_libro_no_contiene_campo_de_ejemplares_disponibles(): void
    {
        $html = $this->get(route('libros.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="ejemplares_disponibles"', $html, 'La disponibilidad solo la calcula el servidor (D-023)');
    }
}
