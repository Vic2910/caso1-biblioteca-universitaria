<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estados vacíos independientes del panel (specs/007-panel-biblioteca,
 * FR-008, D-024): el mensaje exacto «No hay datos disponibles» aparece solo
 * en el indicador sin datos, sin tablas ni filas en esa sección.
 */
class PanelEstadosVaciosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Texto visible de una sección identificada por su rótulo.
     */
    private function textoDeSeccion(string $html, string $titulo): string
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $seccion = $xpath->query('//section[contains(@class, "panel-seccion")][normalize-space(./h3)="'.$titulo.'"]')->item(0);

        return trim($seccion?->textContent ?? '');
    }

    public function test_con_la_base_vacia_las_tres_secciones_muestran_el_mensaje_y_ninguna_tabla(): void
    {
        $html = $this->get('/panel')->assertOk()->getContent();

        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $this->assertSame(3, substr_count($html, 'No hay datos disponibles'), 'Un mensaje por indicador vacío (FR-008)');
        $this->assertSame(0, $xpath->query('//section[contains(@class, "panel")]//table')->length, 'Sin tablas cuando no hay filas');
    }

    public function test_con_datos_parciales_solo_la_seccion_vacia_muestra_el_mensaje(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Con datos']);
        $libro = Libro::factory()->for($categoria)->create(['titulo' => 'Libro con préstamo']);
        Prestamo::factory()->activo()->for($libro)->create();

        $html = $this->get('/panel')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'No hay datos disponibles'));
        $this->assertStringContainsString(
            'No hay datos disponibles',
            $this->textoDeSeccion($html, 'Préstamos vencidos')
        );
        $this->assertStringNotContainsString(
            'No hay datos disponibles',
            $this->textoDeSeccion($html, 'Libros más prestados')
        );
        $this->assertStringNotContainsString(
            'No hay datos disponibles',
            $this->textoDeSeccion($html, 'Ejemplares disponibles por categoría')
        );
    }
}
