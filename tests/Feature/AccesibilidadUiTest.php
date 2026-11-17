<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V-010, V-011 y SC-007: estructura común, un único `h1`, etiquetas
 * asociadas, foco/ARIA y controles nativos en todas las vistas principales
 * y sus formularios (FR-011, D-045).
 */
class AccesibilidadUiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $sesionPorDefecto = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['name' => 'Ana Estudiante']));

        $categoria = Categoria::factory()->create();
        $libro = Libro::factory()->create(['categoria_id' => $categoria->id]);
        Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);
    }

    /**
     * @return list<string>
     */
    private function vistasPrincipales(): array
    {
        return [
            route('catalogo.index'),
            route('libros.index'),
            route('categorias.index'),
            route('prestamos.index'),
            route('panel.index'),
        ];
    }

    /**
     * @return list<string>
     */
    private function vistasConFormularios(): array
    {
        $libro = Libro::firstOrFail();
        $categoria = Categoria::firstOrFail();
        $prestamo = Prestamo::firstOrFail();

        return [
            route('libros.create'),
            route('libros.edit', $libro),
            route('categorias.create'),
            route('categorias.edit', $categoria),
            route('prestamos.create'),
            route('prestamos.form-devolucion', $prestamo),
            route('catalogo.index'),
        ];
    }

    private function htmlDe(string $url): string
    {
        return (string) $this->get($url)->assertOk()->getContent();
    }

    private function extraerRegion(string $html, string $etiqueta, string $clase): string
    {
        $patron = '/<'.$etiqueta.' class="'.$clase.'"[^>]*>.*?<\/'.$etiqueta.'>/s';

        $this->assertMatchesRegularExpression($patron, $html);

        preg_match($patron, $html, $coincidencias);

        return (string) $coincidencias[0];
    }

    private function normalizarRegion(string $region): string
    {
        return (string) preg_replace('/\s+(?:aria-current="page"|class="active")/', '', $region);
    }

    private function assertCamposEtiquetados(string $html): void
    {
        $documento = new DOMDocument;

        libxml_use_internal_errors(true);
        $documento->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($documento);
        $etiquetas = [];

        foreach ($xpath->query('//label[@for]') ?: [] as $etiqueta) {
            $etiquetas[$etiqueta->getAttribute('for')] = true;
        }

        foreach ($xpath->query('//input | //select | //textarea') ?: [] as $campo) {
            $tipo = strtolower($campo->getAttribute('type'));

            if (in_array($tipo, ['hidden', 'submit', 'button', 'reset', 'image'], true)) {
                continue;
            }

            $identificador = $campo->getAttribute('id');

            $this->assertNotSame('', $identificador, 'Todo campo visible debe tener un id.');
            $this->assertArrayHasKey($identificador, $etiquetas, 'Falta una etiqueta <label for> para '.$identificador.'.');
            $this->assertNotSame('', $campo->getAttribute('name'), 'Todo campo visible debe tener name.');
        }
    }

    public function test_todas_las_vistas_tienen_un_solo_h1_y_lengua_espanola(): void
    {
        foreach ($this->vistasPrincipales() as $url) {
            $html = $this->htmlDe($url);

            $this->assertStringContainsString('<html lang="es">', $html);
            $this->assertSame(1, substr_count($html, '<h1'), 'Cada vista debe tener exactamente un h1.');
        }
    }

    public function test_la_cabecera_la_navegacion_y_el_pie_son_identicos_en_todas_las_vistas(): void
    {
        $referencia = null;

        foreach ($this->vistasPrincipales() as $url) {
            $html = $this->htmlDe($url);

            $regiones = [
                $this->normalizarRegion($this->extraerRegion($html, 'header', 'site-header')),
                $this->normalizarRegion($this->extraerRegion($html, 'nav', 'site-nav')),
                $this->normalizarRegion($this->extraerRegion($html, 'footer', 'site-footer')),
            ];

            if ($referencia === null) {
                $referencia = $regiones;

                continue;
            }

            $this->assertSame($referencia, $regiones, 'Las regiones compartidas deben coincidir en '.$url.'.');
        }
    }

    public function test_todos_los_campos_visibles_tienen_etiqueta_asociada(): void
    {
        foreach ($this->vistasConFormularios() as $url) {
            $this->assertCamposEtiquetados($this->htmlDe($url));
        }
    }

    public function test_los_campos_rechazados_llevan_aria_invalid_y_describedby(): void
    {
        $this->from(route('categorias.create'))->post('/categorias', ['nombre' => ''])
            ->assertSessionHasErrors('nombre');

        $html = $this->htmlDe(route('categorias.create'));

        $this->assertMatchesRegularExpression(
            '/<input\b(?=[^>]*\bid="nombre")(?=[^>]*\baria-invalid="true")(?=[^>]*\baria-describedby="error-nombre")[^>]*>/',
            $html
        );
        $this->assertStringContainsString('id="error-nombre"', $html);
    }

    public function test_la_pagina_activa_es_distinguible_y_los_controles_son_nativos(): void
    {
        $html = $this->htmlDe(route('categorias.index'));

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertMatchesRegularExpression('/<button\b[^>]*type="submit"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<(?:div|span|li)\b[^>]*onclick=/i', $html);
    }

    public function test_el_login_tambien_usa_la_estructura_comun_y_campos_etiquetados(): void
    {
        $this->sinSesion();

        $html = $this->htmlDe(route('login'));

        $this->assertStringContainsString('<html lang="es">', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertCamposEtiquetados($html);
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }
}
