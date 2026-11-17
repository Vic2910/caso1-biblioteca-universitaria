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
 * Ejemplares disponibles por categoría del panel (specs/007-panel-biblioteca,
 * FR-003/FR-008, D-021): suma de disponibles, categorías con cero visibles,
 * categorías sin libros ocultas y orden alfabético español.
 */
class PanelDisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Categorías y cifras visibles en la sección de disponibilidad.
     *
     * @return list<list<string>>
     */
    private function disponibilidadDe(string $html): array
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $seccion = $xpath->query('//section[contains(@class, "panel-seccion")][normalize-space(./h3)="Ejemplares disponibles por categoría"]')->item(0);

        $filas = [];

        if ($seccion !== null) {
            foreach ($xpath->query('.//tbody/tr', $seccion) as $fila) {
                $celdas = [];
                foreach ($xpath->query('./td', $fila) as $celda) {
                    $celdas[] = trim($celda->textContent);
                }
                $filas[] = $celdas;
            }
        }

        return $filas;
    }

    /**
     * Texto de una sección del panel, identificado por su encabezado, para
     * acotar aserciones a una sola sección sin afectar a las demás.
     */
    private function textoDeSeccion(string $html, string $encabezado): string
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);
        $seccion = $xpath->query('//section[contains(@class, "panel-seccion")][normalize-space(./h3)="'.$encabezado.'"]')->item(0);

        return $seccion?->textContent ?? '';
    }

    public function test_suma_los_disponibles_oculta_categorias_sin_libros_y_ordena_en_espanol(): void
    {
        $historia = Categoria::factory()->create(['nombre' => 'Historia']);
        $algebra = Categoria::factory()->create(['nombre' => 'Álgebra']);
        $zoologia = Categoria::factory()->create(['nombre' => 'Zoología']);
        $arte = Categoria::factory()->create(['nombre' => 'Arte']);
        Categoria::factory()->create(['nombre' => 'Vacía']);

        Libro::factory()->count(2)->for($historia)->create(['ejemplares_totales' => 3]);
        Libro::factory()->for($historia)->create(['ejemplares_totales' => 4]);
        Libro::factory()->for($algebra)->create(['ejemplares_totales' => 2]);
        Libro::factory()->for($zoologia)->create(['ejemplares_totales' => 0]);
        Libro::factory()->for($arte)->create(['ejemplares_totales' => 1]);

        $filas = $this->disponibilidadDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            [['Álgebra', '2'], ['Arte', '1'], ['Historia', '10'], ['Zoología', '0']],
            $filas,
            '«Álgebra» se ordena como «Algebra» y «Vacía» se oculta al no tener libros (D-021, FR-008)'
        );
    }

    public function test_la_cifra_es_de_ejemplares_disponibles_y_no_de_totales(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Préstamos']);
        $libro = Libro::factory()->for($categoria)->create(['ejemplares_totales' => 5]);

        Prestamo::factory()->count(2)->for($libro)->create();
        $libro->recalcularDisponibilidad();
        $libro->save();

        $filas = $this->disponibilidadDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            [['Préstamos', '3']],
            $filas,
            'El panel suma disponibles (5 − 2), nunca ejemplares_totales (FR-003)'
        );
    }

    public function test_una_categoria_con_libros_y_cero_disponibles_muestra_la_cifra_cero(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Agotada']);
        Libro::factory()->for($categoria)->create(['ejemplares_totales' => 0]);

        $respuesta = $this->get('/panel')->assertOk();

        $this->assertSame([['Agotada', '0']], $this->disponibilidadDe($respuesta->getContent()));
        $this->assertStringNotContainsString(
            'No hay datos disponibles',
            $this->textoDeSeccion($respuesta->getContent(), 'Ejemplares disponibles por categoría'),
            'Un cero es un dato: la sección de disponibilidad no debe mostrar el estado vacío'
        );
    }
}
