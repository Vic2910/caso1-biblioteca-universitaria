<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Búsqueda por el único campo de texto contra título, autor, editorial e
 * ISBN (specs/006-busqueda-catalogo, D-029/D-030): escenarios V-001 y V-002
 * de quickstart.md y criterios SC-001, SC-002 y SC-003.
 */
class BusquedaCatalogoTest extends TestCase
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

    public function test_encuentra_libros_por_coincidencia_parcial_en_el_titulo(): void
    {
        Libro::factory()->create(['titulo' => 'Álgebra lineal']);
        Libro::factory()->create(['titulo' => 'Rayuela']);

        $titulos = $this->titulosEn('/catalogo?q=lgebra');

        $this->assertSame(
            ['Álgebra lineal'],
            $titulos,
            'Debe coincidir solo el libro cuyo título contiene el texto (SC-001)'
        );
    }

    public function test_encuentra_libros_por_coincidencia_parcial_en_el_autor(): void
    {
        Libro::factory()->create(['titulo' => 'Rayuela', 'autor' => 'Julio Cortázar']);
        Libro::factory()->create(['titulo' => 'Quijote', 'autor' => 'Miguel de Cervantes']);

        $titulos = $this->titulosEn('/catalogo?q=julio');

        $this->assertSame(['Rayuela'], $titulos, 'Debe coincidir el libro cuyo autor contiene el texto');
    }

    public function test_encuentra_libros_por_coincidencia_parcial_en_la_editorial(): void
    {
        Libro::factory()->create(['titulo' => 'Física moderna', 'editorial' => 'Editorial Andrómeda']);
        Libro::factory()->create(['titulo' => 'Poesía', 'editorial' => 'Seix Barral']);

        $titulos = $this->titulosEn('/catalogo?q=andr');

        $this->assertSame(['Física moderna'], $titulos, 'Debe coincidir el libro cuya editorial contiene el texto');
    }

    public function test_encuentra_libros_por_coincidencia_parcial_en_el_isbn_con_ceros_iniciales(): void
    {
        Libro::factory()->create(['titulo' => 'Con ceros', 'isbn' => '0012345678905']);
        Libro::factory()->create(['titulo' => 'Sin ceros', 'isbn' => '7777777777777']);

        $titulos = $this->titulosEn('/catalogo?q=0012');

        $this->assertSame(['Con ceros'], $titulos, 'Los ceros iniciales del ISBN deben conservarse (V-002)');

        $titulos = $this->titulosEn('/catalogo?q=5678905');

        $this->assertSame(['Con ceros'], $titulos, 'La coincidencia parcial debe funcionar en cualquier posición');
    }

    public function test_un_mismo_texto_encuentra_libros_por_campos_distintos(): void
    {
        Libro::factory()->create(['titulo' => 'Historia de Roma', 'editorial' => 'Cátedra']);
        Libro::factory()->create(['titulo' => 'Arte y poder', 'editorial' => 'Editorial Historia Viva']);
        Libro::factory()->create(['titulo' => 'Biología celular', 'editorial' => 'Omega']);

        $titulos = $this->titulosEn('/catalogo?q=historia');

        $this->assertSame(
            ['Arte y poder', 'Historia de Roma'],
            $titulos,
            'El texto debe compararse contra los cuatro campos a la vez (SC-003)'
        );
    }

    public function test_la_coincidencia_en_titulo_no_distingue_mayusculas_ni_acentos(): void
    {
        Libro::factory()->create(['titulo' => 'Álgebra lineal']);
        Libro::factory()->create(['titulo' => 'Zoología general']);

        $sinTilde = $this->titulosEn('/catalogo?q=algebra');
        $this->assertSame(['Álgebra lineal'], $sinTilde, 'Sin tildes debe encontrar el título con tilde (SC-001)');

        $enMayusculas = $this->titulosEn('/catalogo?q='.rawurlencode('ALGEBRA'));
        $this->assertSame(['Álgebra lineal'], $enMayusculas, 'En mayúsculas debe encontrar el título en minúsculas');

        $conTilde = $this->titulosEn('/catalogo?q='.rawurlencode('ÁLGEBRA'));
        $this->assertSame(['Álgebra lineal'], $conTilde, 'Con tildes también debe coincidir');
    }

    public function test_la_coincidencia_en_editorial_no_distingue_mayusculas_ni_acentos(): void
    {
        Libro::factory()->create(['titulo' => 'Cálculo', 'editorial' => 'Álgebra Editores']);
        Libro::factory()->create(['titulo' => 'Novela', 'editorial' => 'Planeta']);

        $titulos = $this->titulosEn('/catalogo?q=algebra');

        $this->assertSame(
            ['Cálculo'],
            $titulos,
            'La colación fijada por 006 sobre editorial debe ignorar tildes y mayúsculas (D-030)'
        );
    }

    public function test_el_texto_se_recorta_antes_de_buscar(): void
    {
        Libro::factory()->create(['titulo' => 'Rayuela']);

        $titulos = $this->titulosEn('/catalogo?q='.rawurlencode('  rayuela  '));

        $this->assertSame(['Rayuela'], $titulos, 'El servidor debe aplicar trim() al texto (D-029)');
    }

    public function test_los_comodines_del_texto_se_tratan_como_caracteres_literales(): void
    {
        Libro::factory()->create(['titulo' => 'Registro 100% real']);
        Libro::factory()->create(['titulo' => 'Registro 1000 casos']);

        $titulos = $this->titulosEn('/catalogo?q='.rawurlencode('100%'));

        $this->assertSame(
            ['Registro 100% real'],
            $titulos,
            'El % debe buscarse literalmente y no como comodín de LIKE (D-029)'
        );
    }

    public function test_ningun_libro_ajeno_al_criterio_se_muestra(): void
    {
        Libro::factory()->create(['titulo' => 'Zoología marina']);
        Libro::factory()->count(4)->create();

        $titulos = $this->titulosEn('/catalogo?q=zoolog');

        $this->assertSame(
            ['Zoología marina'],
            $titulos,
            'Los libros sin coincidencia no deben aparecer en la respuesta (SC-002)'
        );
    }
}
