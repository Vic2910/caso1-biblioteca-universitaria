<?php

declare(strict_types=1);

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estructura común del panel (specs/007-panel-biblioteca, FR-006/FR-007,
 * D-018/D-025): una sola vista con las tres secciones, layout compartido con
 * el catálogo, enlace «Panel» activo y ausencia de controles de escritura.
 */
class PanelLayoutTest extends TestCase
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

    public function test_el_panel_responde_200_sin_autenticacion_y_reune_las_tres_secciones(): void
    {
        $respuesta = $this->get('/panel')->assertOk();

        $respuesta->assertSeeInOrder([
            'Panel de la biblioteca',
            'Libros más prestados',
            'Préstamos vencidos',
            'Ejemplares disponibles por categoría',
        ]);
    }

    public function test_el_panel_comparte_cabecera_navegacion_y_pie_con_el_catalogo(): void
    {
        $delPanel = $this->regionesComunesDe($this->get('/panel')->assertOk()->getContent());
        $delCatalogo = $this->regionesComunesDe($this->get('/catalogo')->assertOk()->getContent());

        $this->assertSame($delCatalogo, $delPanel, 'Las tres regiones del layout son idénticas (FR-007)');
    }

    public function test_la_navegacion_comun_incluye_el_enlace_panel_y_lo_marca_como_activo(): void
    {
        $html = $this->get('/panel')->assertOk()->getContent();

        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $enlaces = $xpath->query('//nav[contains(@class, "site-nav")]//ul[contains(@class, "menu-horizontal")]//a[normalize-space(.)="Panel"]');

        $this->assertSame(1, $enlaces->length, 'Debe existir un único enlace «Panel» en la navegación');

        $enlace = $enlaces->item(0);

        $this->assertSame(route('panel.index'), $enlace->getAttribute('href'));
        $this->assertStringContainsString('active', $enlace->getAttribute('class'));

        $documentoCatalogo = new DOMDocument;
        $documentoCatalogo->loadHTML($this->get('/catalogo')->assertOk()->getContent(), LIBXML_NOERROR);
        $xpathCatalogo = new DOMXPath($documentoCatalogo);

        $enlacesCatalogo = $xpathCatalogo->query('//nav[contains(@class, "site-nav")]//ul[contains(@class, "menu-horizontal")]//a[normalize-space(.)="Panel"]');

        $this->assertSame(1, $enlacesCatalogo->length, 'El enlace «Panel» debe estar disponible desde el catálogo');
        $this->assertSame(route('panel.index'), $enlacesCatalogo->item(0)->getAttribute('href'));
        $this->assertStringNotContainsString('active', $enlacesCatalogo->item(0)->getAttribute('class'));
    }

    public function test_el_panel_es_de_solo_consulta_y_identico_entre_dos_consultas(): void
    {
        $primera = $this->get('/panel')->assertOk()->getContent();
        $segunda = $this->get('/panel')->assertOk()->getContent();

        $sinCaducidad = fn (string $html): string => preg_replace('/data-sesion-expira="\d+"/', '', $html);

        $this->assertSame($sinCaducidad($primera), $sinCaducidad($segunda), 'Dos GET consecutivos con los mismos datos devuelven el mismo HTML (V-008)');

        $documento = new DOMDocument;
        $documento->loadHTML($primera, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        // 008 añade el bloque de sesión (formulario de logout) a la cabecera
        // compartida; FR-006 se refiere al contenido del panel, no a ese
        // control global de autenticación.
        $this->assertSame(0, $xpath->query('//main//form')->length, 'El panel no contiene formularios (FR-006)');
        $this->assertSame(0, $xpath->query('//main//button')->length, 'El panel no contiene botones de acción');
        $this->assertSame(0, $xpath->query('//main//a[contains(@class, "boton")]')->length, 'Sin enlaces de escritura');
    }

    public function test_los_metodos_de_escritura_sobre_la_ruta_del_panel_no_estan_disponibles(): void
    {
        $this->post('/panel')->assertStatus(405);
        $this->delete('/panel')->assertStatus(405);
    }
}
