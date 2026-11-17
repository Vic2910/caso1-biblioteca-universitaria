<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el escenario V-008 (layout común) de specs/002-consulta-catalogo/quickstart.md
 * (FR-004, SC-004): cabecera, navegación y pie idénticos en listado, detalle y 404.
 */
class CatalogoLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Extrae las regiones comunes del layout desde el HTML de una respuesta.
     *
     * @return array{cabecera: string, navegacion: string, pie: string}
     */
    private function regionesComunesDe(string $html): array
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $extraer = fn (string $consulta): string => $xpath->query($consulta)->item(0)?->textContent;

        return [
            'cabecera' => $extraer('//header[contains(@class, "site-header")]'),
            'navegacion' => $extraer('//nav[contains(@class, "site-nav")]'),
            'pie' => $extraer('//footer[contains(@class, "site-footer")]'),
        ];
    }

    public function test_listado_detalle_y_404_comparten_cabecera_navegacion_y_pie_identicos(): void
    {
        $libro = Libro::factory()->create();

        $delListado = $this->regionesComunesDe($this->get('/catalogo')->assertOk()->getContent());
        $delDetalle = $this->regionesComunesDe($this->get('/catalogo/'.$libro->id)->assertOk()->getContent());
        $delError = $this->regionesComunesDe($this->get('/catalogo/999999')->assertNotFound()->getContent());

        $this->assertSame($delListado['cabecera'], $delDetalle['cabecera']);
        $this->assertSame($delListado['cabecera'], $delError['cabecera']);

        $this->assertSame($delListado['navegacion'], $delDetalle['navegacion']);
        $this->assertSame($delListado['navegacion'], $delError['navegacion']);

        $this->assertSame($delListado['pie'], $delDetalle['pie']);
        $this->assertSame($delListado['pie'], $delError['pie']);
    }

    public function test_la_navegacion_comun_incluye_el_enlace_de_catalogo_y_el_de_inicio(): void
    {
        $respuesta = $this->get('/catalogo')->assertOk();

        $respuesta->assertSee(route('catalogo.index'));
        $respuesta->assertSee('>Inicio<', false);
        $respuesta->assertSee('>Catálogo<', false);
    }
}
