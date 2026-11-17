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
 * Ranking de libros más prestados del panel (specs/007-panel-biblioteca,
 * FR-001/FR-004, D-019): historial completo, top 5, desempates y determinismo.
 */
class PanelRankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Títulos y cifras visibles en la sección del ranking, en orden de fila.
     *
     * @return array{titulos: list<string>, autores: list<string>, prestamos: list<string>}
     */
    private function rankingDe(string $html): array
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $seccion = $xpath->query('//section[contains(@class, "panel-seccion")][normalize-space(./h3)="Libros más prestados"]')->item(0);

        $titulos = [];
        $autores = [];
        $prestamos = [];

        if ($seccion !== null) {
            foreach ($xpath->query('.//tbody/tr', $seccion) as $fila) {
                $celdas = $xpath->query('./td', $fila);
                $titulos[] = trim($celdas->item(1)?->textContent ?? '');
                $autores[] = trim($celdas->item(2)?->textContent ?? '');
                $prestamos[] = trim($celdas->item(3)?->textContent ?? '');
            }
        }

        return ['titulos' => $titulos, 'autores' => $autores, 'prestamos' => $prestamos];
    }

    public function test_el_ranking_cuenta_el_historial_completo_y_ordena_por_numero_de_prestamos(): void
    {
        $mas = Libro::factory()->create(['titulo' => 'Más prestado']);
        $intermedio = Libro::factory()->create(['titulo' => 'Intermedio']);
        $menos = Libro::factory()->create(['titulo' => 'Menos prestado']);
        Libro::factory()->create(['titulo' => 'Sin préstamos']);

        Prestamo::factory()->count(3)->for($mas)->create();
        Prestamo::factory()->count(2)->for($intermedio)->create();
        Prestamo::factory()->vencido()->for($menos)->create();

        $ranking = $this->rankingDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            ['Más prestado', 'Intermedio', 'Menos prestado'],
            $ranking['titulos'],
            'Cuentan los préstamos devueltos y vencidos del historial completo (FR-004)'
        );
        $this->assertSame(['3', '2', '1'], $ranking['prestamos'], 'La cifra de préstamos debe verse junto a cada libro');
    }

    public function test_el_ranking_muestra_cinco_filas_como_maximo_sin_rellenar_con_libros_sin_prestamos(): void
    {
        for ($posicion = 1; $posicion <= 5; $posicion++) {
            $libro = Libro::factory()->create(['titulo' => 'Con préstamos '.$posicion]);
            Prestamo::factory()->count(6 - $posicion)->for($libro)->create();
        }

        Libro::factory()->create(['titulo' => 'Nunca prestado']);

        $ranking = $this->rankingDe($this->get('/panel')->assertOk()->getContent());

        $this->assertCount(5, $ranking['titulos'], 'El ranking está acotado a cinco posiciones (FR-004)');
        $this->assertNotContains('Nunca prestado', $ranking['titulos'], 'Un libro sin préstamos no ocupa posición');
    }

    public function test_los_empates_se_ordenan_por_titulo_ignorando_tildes_y_mayusculas(): void
    {
        foreach (['Zoología', 'Biología', 'Álgebra', 'arte'] as $titulo) {
            $libro = Libro::factory()->create(['titulo' => $titulo]);
            Prestamo::factory()->for($libro)->create();
        }

        $ranking = $this->rankingDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            ['Álgebra', 'arte', 'Biología', 'Zoología'],
            $ranking['titulos'],
            'La colación ai_ci ordena «Álgebra» antes que «arte» y «Biología» antes que «Zoología» (D-019)'
        );
    }

    public function test_el_isbn_resuelve_el_empate_de_titulos_y_el_orden_es_determinista(): void
    {
        foreach (['3000000000001' => 'Autor 3', '1000000000001' => 'Autor 1', '2000000000001' => 'Autor 2'] as $isbn => $autor) {
            $libro = Libro::factory()->create(['titulo' => 'Título compartido', 'isbn' => $isbn, 'autor' => $autor]);
            Prestamo::factory()->for($libro)->create();
        }

        $primera = $this->rankingDe($this->get('/panel')->assertOk()->getContent());
        $segunda = $this->rankingDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            ['Autor 1', 'Autor 2', 'Autor 3'],
            $primera['autores'],
            'Con títulos idénticos decide el ISBN, identificable aquí por su autor (D-019)'
        );
        $this->assertSame($primera, $segunda, 'Dos consultas con los mismos datos devuelven el mismo orden (FR-004)');
        $this->assertSame(['1', '1', '1'], $primera['prestamos'], 'Cada libro empatado aporta un préstamo');
    }
}
