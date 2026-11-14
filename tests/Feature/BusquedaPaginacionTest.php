<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paginación de 10 sobre el conjunto filtrado, conservación de los criterios
 * en los enlaces y 404 de página evaluado sobre el total filtrado
 * (specs/006-busqueda-catalogo, FR-005, D-013/D-034): escenario V-008.
 */
class BusquedaPaginacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea 12 libros «Buscable NN» en una categoría y 5 «Oculto NN» en otra,
     * de modo que un criterio deje más de una página de resultados.
     *
     * @return array{buscable: Categoria, ajena: Categoria}
     */
    private function sembrar(): array
    {
        $buscable = Categoria::factory()->create(['nombre' => 'Referencia']);
        $ajena = Categoria::factory()->create(['nombre' => 'Ficción']);

        for ($i = 1; $i <= 12; $i++) {
            Libro::factory()->create([
                'titulo' => 'Buscable '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'categoria_id' => $buscable->id,
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Libro::factory()->create([
                'titulo' => 'Oculto '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'categoria_id' => $ajena->id,
            ]);
        }

        return ['buscable' => $buscable, 'ajena' => $ajena];
    }

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
     * Destinos de los enlaces de paginación renderizados en el HTML.
     *
     * @return list<string>
     */
    private function hrefsDePaginacion(string $html): array
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $hrefs = [];
        foreach ($xpath->query('//nav[contains(@class, "paginacion")]//a/@href') as $nodo) {
            $hrefs[] = $nodo->nodeValue;
        }

        return $hrefs;
    }

    public function test_la_paginacion_de_diez_se_aplica_al_conjunto_filtrado(): void
    {
        $this->sembrar();

        $pagina1 = $this->get('/catalogo?q=Buscable')->assertOk();
        $pagina2 = $this->get('/catalogo?q=Buscable&page=2')->assertOk();

        $titulos1 = array_column($this->tarjetasDe($pagina1->getContent()), 'titulo');
        $titulos2 = array_column($this->tarjetasDe($pagina2->getContent()), 'titulo');

        $this->assertCount(10, $titulos1, 'La página filtrada debe mostrar 10 tarjetas');
        $this->assertSame(
            array_map(static fn (int $i): string => 'Buscable '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), range(1, 10)),
            $titulos1,
            'El orden filtrado sigue siendo titulo → autor → isbn'
        );

        $this->assertCount(2, $titulos2, 'La última página filtrada muestra solo los 2 libros restantes');
        $this->assertSame(['Buscable 11', 'Buscable 12'], $titulos2);

        $pagina1->assertSee('Siguiente');
        $pagina2->assertSee('Anterior');
    }

    public function test_los_enlaces_de_paginacion_conservan_el_texto_y_la_categoria(): void
    {
        $categorias = $this->sembrar();

        $respuesta = $this->get('/catalogo?q=Buscable&categoria='.$categorias['buscable']->id)->assertOk();

        $hrefs = $this->hrefsDePaginacion($respuesta->getContent());

        $this->assertNotSame([], $hrefs, 'Con más de 10 resultados deben renderizarse enlaces de paginación');

        foreach ($hrefs as $href) {
            $this->assertStringContainsString('q=Buscable', $href, 'Cada enlace debe conservar el texto buscado');
            $this->assertStringContainsString(
                'categoria='.$categorias['buscable']->id,
                $href,
                'Cada enlace debe conservar la categoría (withQueryString, D-034)'
            );
        }
    }

    public function test_bajo_filtro_la_pagina_fuera_de_rango_o_no_numerica_responde_404(): void
    {
        $this->sembrar();

        $fueraDeRango = $this->get('/catalogo?q=Buscable&page=999');
        $fueraDeRango->assertStatus(404);
        $fueraDeRango->assertSee('Página no válida');

        $noNumerica = $this->get('/catalogo?q=Buscable&page=abc');
        $noNumerica->assertStatus(404);
        $noNumerica->assertSee('Página no válida');
    }

    public function test_bajo_filtro_la_ultima_pagina_valida_se_calcula_sobre_el_total_filtrado(): void
    {
        $this->sembrar();

        $pagina2 = $this->get('/catalogo?q=Buscable&page=2');
        $pagina2->assertOk();

        $pagina3 = $this->get('/catalogo?q=Buscable&page=3');
        $pagina3->assertStatus(404);
        $pagina3->assertSee('Página no válida');
    }

    public function test_con_criterio_sin_resultados_la_pagina_uno_es_valida_y_la_dos_es_404(): void
    {
        Libro::factory()->create();

        $pagina1 = $this->get('/catalogo?q=zzzznoexiste');
        $pagina1->assertOk();
        $pagina1->assertSee('No se encontraron libros que coincidan con la búsqueda');

        $pagina2 = $this->get('/catalogo?q=zzzznoexiste&page=2');
        $pagina2->assertStatus(404);
        $pagina2->assertSee('Página no válida');
    }
}
