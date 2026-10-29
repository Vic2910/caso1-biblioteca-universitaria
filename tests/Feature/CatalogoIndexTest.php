<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cubre los escenarios V-001 (listado y tarjetas) y V-006 (catálogo vacío)
 * de specs/002-consulta-catalogo/quickstart.md (FR-001, FR-003, FR-004).
 */
class CatalogoIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Extrae las tarjetas del listado desde el HTML de la respuesta.
     *
     * @return list<array{href: string, titulo: string, texto: string}>
     */
    private function tarjetasDe(string $html): array
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $tarjetas = [];
        foreach ($xpath->query('//a[contains(@class, "tarjeta-libro")]') as $nodo) {
            $titulo = $xpath->query('.//h3', $nodo)->item(0);

            $tarjetas[] = [
                'href' => $nodo->getAttribute('href'),
                'titulo' => $titulo !== null ? trim($titulo->textContent) : '',
                'texto' => trim($nodo->textContent),
            ];
        }

        return $tarjetas;
    }

    /**
     * Cuenta los elementos de paginación renderizados en el HTML.
     */
    private function cuentaPaginacion(string $html): int
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);

        return (new DOMXPath($documento))
            ->query('//nav[contains(@class, "paginacion")]')
            ->length;
    }

    public function test_el_listado_responde_200_con_titulo_de_seccion(): void
    {
        Libro::factory()->create();

        $respuesta = $this->get('/catalogo');

        $respuesta->assertOk();
        $respuesta->assertSee('Catálogo de libros');
    }

    public function test_cada_tarjeta_muestra_los_campos_en_orden_y_usa_la_portada_predeterminada(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Ciencias exactas']);
        $libro = Libro::factory()->create([
            'titulo' => 'Álgebra lineal',
            'autor' => 'David Lay',
            'anio_publicacion' => 2012,
            'categoria_id' => $categoria->id,
            'portada' => null,
        ]);

        $respuesta = $this->get('/catalogo');

        $respuesta->assertOk();
        $respuesta->assertSeeInOrder([
            asset('images/libro-sin-portada.svg'),
            'Sin portada',
            $libro->titulo,
            $libro->autor,
            (string) $libro->anio_publicacion,
            $categoria->nombre,
        ]);
    }

    public function test_toda_la_tarjeta_es_un_enlace_clicable_a_la_ficha_incluyendo_titulo_y_autor(): void
    {
        $libro = Libro::factory()->create([
            'titulo' => 'Rayuela',
            'autor' => 'Julio Cortázar',
        ]);

        $respuesta = $this->get('/catalogo')->assertOk();

        $tarjetas = $this->tarjetasDe($respuesta->getContent());

        $this->assertCount(1, $tarjetas, 'Debe haber exactamente una tarjeta enlazable por libro');
        $this->assertSame(
            route('catalogo.show', $libro),
            $tarjetas[0]['href'],
            'La tarjeta entera debe enlazar a la ficha del libro'
        );
        $this->assertStringContainsString('Rayuela', $tarjetas[0]['texto']);
        $this->assertStringContainsString('Julio Cortázar', $tarjetas[0]['texto']);
    }

    public function test_la_tarjeta_con_portada_registrada_usa_storage_con_respaldo_predeterminado(): void
    {
        Libro::factory()->create(['portada' => 'portadas/ejemplo.jpg']);

        $respuesta = $this->get('/catalogo')->assertOk();

        $respuesta->assertSee(Storage::disk('public')->url('portadas/ejemplo.jpg'), false);
        $respuesta->assertSee('onerror="this.onerror=null', false);
    }

    public function test_el_estado_vacio_se_muestra_sin_tarjetas_ni_controles_de_paginacion(): void
    {
        $respuesta = $this->get('/catalogo');

        $respuesta->assertOk();
        $respuesta->assertSee('No hay libros registrados aún');
        $respuesta->assertDontSee('Siguiente');
        $respuesta->assertDontSee('Anterior');

        $this->assertSame([], $this->tarjetasDe($respuesta->getContent()), 'El estado vacío no debe mostrar tarjetas');
        $this->assertSame(0, $this->cuentaPaginacion($respuesta->getContent()), 'El estado vacío no debe mostrar paginación');
    }
}
