<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\Prestamo;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los escenarios V-010 y V-013 de specs/004-gestion-prestamos/
 * quickstart.md (FR-001, FR-002, FR-006, SC-003, SC-004): datos y estados en
 * el listado paginado (10 por página, orden `fecha_prestamo` DESC → `id`
 * DESC), botón de devolución solo donde corresponde, estado vacío y
 * cabecera/navegación/pie idénticos al catálogo.
 */
class PrestamoListadoTest extends TestCase
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

    /**
     * Cuenta las filas de la tabla de préstamos del listado.
     */
    private function filasDePrestamos(string $html): int
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        return $xpath->query('//table[contains(@class, "tabla-prestamos")]/tbody/tr')->length;
    }

    public function test_el_listado_muestra_los_datos_el_estado_y_la_accion_donde_corresponde(): void
    {
        $libro = Libro::factory()->create(['titulo' => 'Ficciones', 'isbn' => '9788420536477']);
        $activo = Prestamo::factory()->activo()->create([
            'libro_id' => $libro->id,
            'nombre_estudiante' => 'Ana Martínez López',
            'carnet_estudiante' => '202400123',
        ]);

        $respuesta = $this->get('/prestamos');

        $respuesta->assertOk();
        $respuesta->assertSee('Ficciones');
        $respuesta->assertSee('9788420536477');
        $respuesta->assertSee('Ana Martínez López');
        $respuesta->assertSee('202400123');
        $respuesta->assertSee($activo->fecha_prestamo->format('d/m/Y'), false);
        $respuesta->assertSee($activo->fecha_devolucion_prevista->format('d/m/Y'), false);
        $respuesta->assertSee('—', false);
        $respuesta->assertSee('estado-activo', false);
        $respuesta->assertSee('>Activo<', false);
        $respuesta->assertSee(route('prestamos.form-devolucion', $activo));
        $respuesta->assertSee('Registrar devolución');
    }

    public function test_las_filas_devueltas_no_ofrecen_accion_de_devolucion(): void
    {
        $libro = Libro::factory()->create();
        $devuelto = Prestamo::factory()->devuelto()->create(['libro_id' => $libro->id]);

        $html = $this->get('/prestamos')->assertOk()->getContent();

        $this->assertStringContainsString('estado-devuelto', $html);
        $this->assertStringContainsString('Devuelto', $html);
        $this->assertStringNotContainsString(route('prestamos.form-devolucion', $devuelto), $html);
    }

    public function test_el_listado_pagina_10_prestamos_por_pagina_de_forma_navegable(): void
    {
        $libro = Libro::factory()->create();

        foreach (range(1, 12) as $dias) {
            Prestamo::factory()->create([
                'libro_id' => $libro->id,
                'carnet_estudiante' => '9000000'.str_pad((string) $dias, 2, '0', STR_PAD_LEFT),
                'fecha_prestamo' => now()->subDays($dias)->format('Y-m-d'),
                'fecha_devolucion_prevista' => now()->addDays(14 - $dias)->format('Y-m-d'),
            ]);
        }

        $primera = $this->get('/prestamos?page=1');
        $primera->assertOk();
        $this->assertSame(10, $this->filasDePrestamos($primera->getContent()), 'La primera página muestra 10 préstamos');
        $primera->assertSee('900000001', false);
        $primera->assertSee('900000010', false);
        $primera->assertDontSee('900000011', false);
        $primera->assertSee(route('prestamos.index').'?page=2', false);

        $segunda = $this->get('/prestamos?page=2');
        $segunda->assertOk();
        $this->assertSame(2, $this->filasDePrestamos($segunda->getContent()), 'La segunda página muestra el resto');
        $segunda->assertSee('900000011', false);
        $segunda->assertSee('900000012', false);
        $segunda->assertDontSee('900000001', false);
        $segunda->assertSee('Anterior', false);
    }

    public function test_el_orden_es_determinista_por_fecha_de_prestamo_desc_luego_por_id_desc(): void
    {
        $libro = Libro::factory()->create();

        // Misma fecha que el segundo: desempata por id DESC → va después.
        $deMenorId = Prestamo::factory()->create([
            'libro_id' => $libro->id,
            'carnet_estudiante' => '700000001',
            'fecha_prestamo' => now()->format('Y-m-d'),
        ]);
        $deMayorId = Prestamo::factory()->create([
            'libro_id' => $libro->id,
            'carnet_estudiante' => '700000002',
            'fecha_prestamo' => now()->format('Y-m-d'),
        ]);
        $masAntiguo = Prestamo::factory()->create([
            'libro_id' => $libro->id,
            'carnet_estudiante' => '700000003',
            'fecha_prestamo' => now()->subDays(10)->format('Y-m-d'),
        ]);

        $html = $this->get('/prestamos')->assertOk()->getContent();

        $posicionDeMenorId = strpos($html, $deMenorId->carnet_estudiante);
        $posicionDeMayorId = strpos($html, $deMayorId->carnet_estudiante);
        $posicionAntiguo = strpos($html, $masAntiguo->carnet_estudiante);

        $this->assertLessThan($posicionDeMenorId, $posicionDeMayorId, 'Con la misma fecha, el mayor id aparece primero');
        $this->assertLessThan($posicionAntiguo, $posicionDeMenorId, 'La fecha más antigua aparece después');
    }

    public function test_sin_prestamos_muestra_el_estado_vacio_sin_tabla_ni_paginacion(): void
    {
        $respuesta = $this->get('/prestamos');

        $respuesta->assertOk();
        $respuesta->assertSee('No hay préstamos registrados aún');
        $respuesta->assertDontSee('<table', false);
        $respuesta->assertDontSee('aria-label="Paginación de préstamos"', false);
        $respuesta->assertDontSee('Anterior', false);
        $this->assertSame(0, $this->filasDePrestamos($respuesta->getContent()));
    }

    public function test_comparte_cabecera_navegacion_y_pie_con_el_catalogo(): void
    {
        Prestamo::factory()->activo()->create(['libro_id' => Libro::factory()->create()->id]);

        $delListado = $this->regionesComunesDe($this->get('/prestamos')->assertOk()->getContent());
        $delCatalogo = $this->regionesComunesDe($this->get('/catalogo')->assertOk()->getContent());

        $this->assertSame($delCatalogo['cabecera'], $delListado['cabecera']);
        $this->assertSame($delCatalogo['navegacion'], $delListado['navegacion']);
        $this->assertSame($delCatalogo['pie'], $delListado['pie']);

        $this->get('/prestamos')
            ->assertSee('>Préstamos<', false)
            ->assertSee(route('prestamos.index'));
    }
}
