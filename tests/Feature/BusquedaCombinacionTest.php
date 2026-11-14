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
 * Combinación de criterios con regla Y, filtro exacto de categoría,
 * valores de `categoria` inválidos y criterios vacíos (specs/006-busqueda-catalogo,
 * FR-003/FR-005, D-029/D-031): escenarios V-003 y V-005 y criterio SC-005.
 */
class BusquedaCombinacionTest extends TestCase
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
     * Títulos visibles en el listado devuelto por la ruta indicada.
     *
     * @return list<string>
     */
    private function titulosEn(string $ruta): array
    {
        $respuesta = $this->get($ruta)->assertOk();

        return array_column($this->tarjetasDe($respuesta->getContent()), 'titulo');
    }

    public function test_el_filtro_de_categoria_muestra_solo_los_libros_de_esa_categoria(): void
    {
        $matematicas = Categoria::factory()->create(['nombre' => 'Matemáticas']);
        $literatura = Categoria::factory()->create(['nombre' => 'Literatura']);

        Libro::factory()->create(['titulo' => 'Cálculo diferencial', 'categoria_id' => $matematicas->id]);
        Libro::factory()->create(['titulo' => 'Cien años de soledad', 'categoria_id' => $literatura->id]);

        $titulos = $this->titulosEn('/catalogo?categoria='.$matematicas->id);

        $this->assertSame(
            ['Cálculo diferencial'],
            $titulos,
            'El filtro de categoría es de coincidencia exacta sobre categoria_id (FR-003)'
        );
    }

    public function test_el_texto_y_la_categoria_se_combinan_con_regla_y(): void
    {
        $ciencias = Categoria::factory()->create(['nombre' => 'Ciencias']);
        $arte = Categoria::factory()->create(['nombre' => 'Arte']);

        Libro::factory()->create(['titulo' => 'Historia de la ciencia', 'categoria_id' => $ciencias->id]);
        Libro::factory()->create(['titulo' => 'Historia del arte', 'categoria_id' => $arte->id]);
        Libro::factory()->create(['titulo' => 'Física cuántica', 'categoria_id' => $ciencias->id]);

        $titulos = $this->titulosEn('/catalogo?q=historia&categoria='.$ciencias->id);

        $this->assertSame(
            ['Historia de la ciencia'],
            $titulos,
            'Debe mostrarse solo quien cumple texto Y categoría a la vez (SC-005)'
        );
    }

    public function test_un_categoria_invalida_o_inexistente_se_ignora_y_muestra_el_catalogo_completo(): void
    {
        $categoria = Categoria::factory()->create();
        $otra = Categoria::factory()->create();

        Libro::factory()->count(3)->create(['categoria_id' => $categoria->id]);
        Libro::factory()->create(['categoria_id' => $otra->id]);

        foreach (['9999', 'abc', '0', '-3', '1,5', ''] as $valor) {
            $titulos = $this->titulosEn('/catalogo?categoria='.rawurlencode($valor));

            $this->assertCount(
                4,
                $titulos,
                "categoria=\"{$valor}\" no es un criterio válido y debe ignorarse (D-031)"
            );
        }
    }

    public function test_el_texto_vacio_o_solo_de_espacios_no_aplica_criterio(): void
    {
        $historia = Categoria::factory()->create(['nombre' => 'Historia']);
        $novela = Categoria::factory()->create(['nombre' => 'Novela']);

        Libro::factory()->create(['titulo' => 'Guerra y paz', 'categoria_id' => $historia->id]);
        Libro::factory()->create(['titulo' => 'El arte de la guerra', 'categoria_id' => $historia->id]);
        Libro::factory()->create(['titulo' => 'Anna Karénina', 'categoria_id' => $novela->id]);

        foreach (['', '   ', "\t\n"] as $texto) {
            $titulos = $this->titulosEn('/catalogo?q='.rawurlencode($texto));

            $this->assertCount(
                3,
                $titulos,
                'q vacío o de solo espacios debe devolver el catálogo completo sin residuos (V-005)'
            );
        }

        $titulos = $this->titulosEn('/catalogo?q='.rawurlencode('   ').'&categoria='.$historia->id);

        $this->assertSame(
            ['El arte de la guerra', 'Guerra y paz'],
            $titulos,
            'El texto vacío no aporta criterio pero el de categoría sigue aplicándose (regla Y sobre lo activo)'
        );
    }
}
