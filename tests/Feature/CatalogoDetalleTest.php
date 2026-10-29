<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los escenarios V-003 (portada predeterminada), V-004 (ficha completa)
 * y V-005 (libro inexistente → 404) de specs/002-consulta-catalogo/quickstart.md
 * (FR-002, FR-003, SC-002, SC-003).
 */
class CatalogoDetalleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ficha_muestra_los_ocho_campos_etiquetados_en_orden_y_en_espanol(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Ciencias exactas']);
        $libro = Libro::factory()->create([
            'titulo' => 'Álgebra lineal y sus aplicaciones',
            'autor' => 'David Lay',
            'editorial' => 'Pearson',
            'anio_publicacion' => 2012,
            'isbn' => '9780321982384',
            'ejemplares_totales' => 4,
            'ejemplares_disponibles' => 3,
            'categoria_id' => $categoria->id,
            'portada' => null,
        ]);

        $respuesta = $this->get('/catalogo/'.$libro->id);

        $respuesta->assertOk();
        $respuesta->assertSee('Ficha del libro');
        $respuesta->assertSeeInOrder([
            'Título',
            $libro->titulo,
            'Autor',
            $libro->autor,
            'Editorial',
            $libro->editorial,
            'Año de publicación',
            (string) $libro->anio_publicacion,
            'ISBN',
            $libro->isbn,
            'Total de ejemplares',
            (string) $libro->ejemplares_totales,
            'Ejemplares disponibles',
            (string) $libro->ejemplares_disponibles,
            'Categoría',
            $categoria->nombre,
        ]);
    }

    public function test_la_ficha_usa_la_misma_portada_predeterminada_que_el_listado(): void
    {
        $libro = Libro::factory()->create(['portada' => null]);

        $respuesta = $this->get('/catalogo/'.$libro->id);

        $respuesta->assertOk();
        $respuesta->assertSee(asset('images/libro-sin-portada.svg'));
        $respuesta->assertSee('Sin portada');
    }

    public function test_la_ficha_incluye_el_enlace_de_vuelta_al_catalogo(): void
    {
        $libro = Libro::factory()->create();

        $respuesta = $this->get('/catalogo/'.$libro->id);

        $respuesta->assertOk();
        $respuesta->assertSee('Volver al catálogo');
        $respuesta->assertSee(route('catalogo.index'));
    }

    public function test_un_libro_inexistente_responde_404_con_vista_personalizada(): void
    {
        $respuesta = $this->get('/catalogo/999999');

        $respuesta->assertNotFound();
        $respuesta->assertSee('Libro no encontrado');
        $respuesta->assertSee('Volver al catálogo');
        $respuesta->assertSee(route('catalogo.index'));
        $respuesta->assertSee('Biblioteca Universitaria', false);
    }
}
