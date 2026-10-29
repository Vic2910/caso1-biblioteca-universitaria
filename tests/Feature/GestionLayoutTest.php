<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cubre el escenario V-012 de specs/003-gestion-libros/quickstart.md (SC-004):
 * todas las vistas de gestión comparten el layout con la misma cabecera,
 * navegación y pie; todo formulario lleva `@csrf` y los de edición/borrado
 * `@method`; un error de validación forzado se muestra junto a su campo y
 * conserva lo introducido con `old()`.
 */
class GestionLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rutas de gestión que deben compartir el layout completo.
     *
     * @return array<string, array{0: string}>
     */
    public static function rutasDeGestion(): array
    {
        return [
            'alta de libro' => ['/libros/crear'],
            'listado de categorías' => ['/categorias'],
            'alta de categoría' => ['/categorias/crear'],
            'catálogo' => ['/catalogo'],
        ];
    }

    #[DataProvider('rutasDeGestion')]
    public function test_las_vistas_de_gestion_comparten_cabecera_navegacion_y_pie(string $ruta): void
    {
        $this->recorrerCompartenLayout($this->get($ruta));
    }

    public function test_las_vistas_de_edicion_de_libro_y_categoria_comparten_el_layout(): void
    {
        $libro = Libro::factory()->create();
        $categoria = Categoria::factory()->create();

        $this->recorrerCompartenLayout($this->get('/libros/'.$libro->id.'/editar'));
        $this->recorrerCompartenLayout($this->get('/categorias/'.$categoria->id.'/editar'));
    }

    public function test_las_vistas_de_detalle_de_libro_y_listado_comparten_el_layout(): void
    {
        $libro = Libro::factory()->create();

        $this->recorrerCompartenLayout($this->get('/catalogo/'.$libro->id));
    }

    public function test_los_formularios_de_alta_llevan_csrf(): void
    {
        $this->assertContieneCsrf($this->get('/libros/crear')->assertOk()->getContent());
        $this->assertContieneCsrf($this->get('/categorias/crear')->assertOk()->getContent());
    }

    public function test_los_formularios_de_edicion_llevan_csrf_y_method_put(): void
    {
        $libro = Libro::factory()->create();
        $categoria = Categoria::factory()->create();

        $htmlLibro = $this->get('/libros/'.$libro->id.'/editar')->assertOk()->getContent();
        $htmlCategoria = $this->get('/categorias/'.$categoria->id.'/editar')->assertOk()->getContent();

        $this->assertContieneCsrf($htmlLibro);
        $this->assertContieneCsrf($htmlCategoria);
        $this->assertStringContainsString('name="_method"', $htmlLibro, 'El formulario de edición de libro debe declarar @method PUT');
        $this->assertStringContainsString('value="PUT"', $htmlLibro);
        $this->assertStringContainsString('name="_method"', $htmlCategoria, 'El formulario de edición de categoría debe declarar @method PUT');
        $this->assertStringContainsString('value="PUT"', $htmlCategoria);
    }

    public function test_los_formularios_de_eliminar_llevan_csrf_y_method_delete(): void
    {
        $libro = Libro::factory()->create();
        $categoria = Categoria::factory()->create();

        $htmlLibro = $this->get('/catalogo/'.$libro->id)->assertOk()->getContent();
        $htmlCategoria = $this->get('/categorias')->assertOk()->getContent();

        $this->assertStringContainsString('name="_method"', $htmlLibro, 'El formulario de borrado de libro debe declarar @method DELETE');
        $this->assertStringContainsString('value="DELETE"', $htmlLibro);
        $this->assertStringContainsString('name="_method"', $htmlCategoria, 'El formulario de borrado de categoría debe declarar @method DELETE');
        $this->assertStringContainsString('value="DELETE"', $htmlCategoria);
    }

    public function test_un_error_de_validacion_se_muestra_junto_a_su_campo_y_conserva_lo_introducido(): void
    {
        $html = $this->followingRedirects()
            ->from(route('libros.create'))
            ->post(route('libros.store'), [
                'isbn' => '123',
                'titulo' => '',
                'autor' => '',
                'editorial' => '',
                'anio_publicacion' => 1800,
                'ejemplares_totales' => 0,
                'categoria_id' => 99999,
            ])
            ->assertOk()
            ->getContent();

        foreach ($this->camposDelLibro() as $campo) {
            $this->assertErrorJuntoAlCampo($html, $campo, 'El formulario debe mostrar el error del campo '.$campo.' justo debajo');
        }

        $this->assertStringContainsString('value="123"', $html, 'El ISBN introducido debe conservarse con old()');
        $this->assertStringContainsString('value="1800"', $html, 'El año introducido debe conservarse con old()');
        $this->assertStringContainsString('value="0"', $html, 'Los ejemplares introducidos deben conservarse con old()');
    }

    public function test_un_error_de_categoria_se_muestra_junto_al_campo_y_conserva_lo_introducido(): void
    {
        Categoria::factory()->create(['nombre' => 'Matemáticas']);

        $html = $this->followingRedirects()
            ->from(route('categorias.create'))
            ->post(route('categorias.store'), ['nombre' => '  Matemáticas  '])
            ->assertOk()
            ->getContent();

        $this->assertErrorJuntoAlCampo($html, 'nombre', 'El formulario de categoría debe mostrar el error justo debajo del campo');
        $this->assertStringContainsString('value="Matemáticas"', $html, 'El nombre introducido debe conservarse con old() recortado (igual que lo validó el servidor)');
    }

    /**
     * Comprueba la presencia de la cabecera, la navegación y el pie comunes
     * (contracts/ui-structure §1) en la respuesta indicada.
     */
    private function recorrerCompartenLayout($respuesta): void
    {
        $respuesta->assertOk();

        $respuesta->assertSee('<h1>Biblioteca Universitaria</h1>', false);
        $respuesta->assertSee(route('catalogo.index'), false);
        $respuesta->assertSee(route('categorias.index'), false);
        $respuesta->assertSee('<footer class="site-footer">', false);
    }

    private function assertContieneCsrf(string $html): void
    {
        $this->assertStringContainsString('name="_token"', $html, 'Todo formulario debe incluir el token CSRF (@csrf)');
    }

    /**
     * @return list<string>
     */
    private function camposDelLibro(): array
    {
        return ['isbn', 'titulo', 'autor', 'editorial', 'anio_publicacion', 'ejemplares_totales', 'categoria_id'];
    }

    /**
     * Comprueba que el mensaje de validación aparece dentro del mismo bloque
     * de campo que su input (SC-004: «junto al campo», no solo al inicio).
     */
    private function assertErrorJuntoAlCampo(string $html, string $campo, string $mensaje): void
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $nodos = $xpath->query(sprintf("//*[@name='%s']/ancestor::div[contains(@class, 'campo-formulario')][1]", $campo));

        $this->assertNotFalse($nodos, 'No se pudo buscar el campo '.$campo);
        $this->assertGreaterThan(0, $nodos->length, 'No se encontró el campo '.$campo);

        $bloque = $nodos->item(0);

        $this->assertNotNull($bloque);

        $xmlDelBloque = (string) $bloque->ownerDocument->saveXML($bloque);

        $this->assertStringContainsString('error-campo', $xmlDelBloque, $mensaje);
    }
}
