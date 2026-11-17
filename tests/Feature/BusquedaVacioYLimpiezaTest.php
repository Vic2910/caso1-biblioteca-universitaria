<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estados vacíos diferenciados, criterios visibles y enlace «Limpiar»
 * (specs/006-busqueda-catalogo, FR-004/FR-006, D-033): escenarios V-004,
 * V-006 y V-009 y criterios SC-002 y SC-004.
 */
class BusquedaVacioYLimpiezaTest extends TestCase
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
     * Destino del enlace «Limpiar» del listado, o null si no está presente.
     */
    private function enlaceLimpiar(string $html): ?string
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $enlaces = $xpath->query('//a[normalize-space(text())="Limpiar"]');

        if ($enlaces === false || $enlaces->length === 0) {
            return null;
        }

        $enlace = $enlaces->item(0);
        assert($enlace instanceof DOMElement);

        return $enlace->getAttribute('href');
    }

    /**
     * Valor de la categoría marcada en el desplegable, o null si no hay ninguna.
     */
    private function categoriaMarcada(string $html): ?string
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $marcadas = $xpath->query('//select[@name="categoria"]/option[@selected]');

        if ($marcadas === false || $marcadas->length === 0) {
            return null;
        }

        $opcion = $marcadas->item(0);
        assert($opcion instanceof DOMElement);

        return $opcion->getAttribute('value');
    }

    public function test_sin_coincidencias_muestra_el_mensaje_exacto_con_los_criterios_visibles(): void
    {
        Libro::factory()->create(['titulo' => 'Rayuela']);

        $respuesta = $this->get('/catalogo?q=zzzznoexiste');

        $respuesta->assertOk();
        $respuesta->assertSee('No se encontraron libros que coincidan con la búsqueda');
        $respuesta->assertDontSee('No hay libros registrados aún');
        $respuesta->assertSee('value="zzzznoexiste"', false, 'El criterio debe seguir visible en el campo');
        $respuesta->assertDontSee('Anterior');
        $respuesta->assertDontSee('Siguiente');

        $this->assertSame(
            [],
            $this->tarjetasDe($respuesta->getContent()),
            'El estado sin coincidencias no debe mostrar tarjetas (V-004)'
        );
    }

    public function test_sin_coincidencias_por_categoria_tambien_muestra_el_mensaje(): void
    {
        $sinEjemplares = Categoria::factory()->create(['nombre' => 'Sin ejemplares']);
        Libro::factory()->create();

        $respuesta = $this->get('/catalogo?categoria='.$sinEjemplares->id);

        $respuesta->assertOk();
        $respuesta->assertSee('No se encontraron libros que coincidan con la búsqueda');
        $respuesta->assertDontSee('No hay libros registrados aún');

        $this->assertSame(
            (string) $sinEjemplares->id,
            $this->categoriaMarcada($respuesta->getContent()),
            'El criterio de categoría debe seguir visible y marcado'
        );
        $this->assertSame([], $this->tarjetasDe($respuesta->getContent()));
    }

    public function test_sin_libros_y_sin_criterio_se_conserva_el_mensaje_heredado(): void
    {
        $respuesta = $this->get('/catalogo');

        $respuesta->assertOk();
        $respuesta->assertSee('No hay libros registrados aún');
        $respuesta->assertDontSee('No se encontraron libros que coincidan con la búsqueda');
        $this->assertNull(
            $this->enlaceLimpiar($respuesta->getContent()),
            'Sin criterios no debe haber enlace «Limpiar» (V-009)'
        );
    }

    public function test_un_texto_solo_de_espacios_sigue_sin_ser_criterio_en_un_catalogo_vacio(): void
    {
        $respuesta = $this->get('/catalogo?q='.rawurlencode('   '));

        $respuesta->assertOk();
        $respuesta->assertSee('No hay libros registrados aún');
        $respuesta->assertDontSee('No se encontraron libros que coincidan con la búsqueda');
    }

    public function test_el_enlace_limpiar_solo_aparece_con_algun_criterio_activos(): void
    {
        $categoria = Categoria::factory()->create();
        Libro::factory()->create(['categoria_id' => $categoria->id]);

        $sinCriterio = $this->get('/catalogo')->assertOk();
        $this->assertNull(
            $this->enlaceLimpiar($sinCriterio->getContent()),
            'Con el listado completo no hay nada que limpiar'
        );

        $conTexto = $this->get('/catalogo?q=zzzznoexiste')->assertOk();
        $this->assertSame(
            route('catalogo.index'),
            $this->enlaceLimpiar($conTexto->getContent()),
            '«Limpiar» debe apuntar al catálogo sin parámetros (SC-004)'
        );

        $conCategoria = $this->get('/catalogo?categoria='.$categoria->id)->assertOk();
        $this->assertSame(
            route('catalogo.index'),
            $this->enlaceLimpiar($conCategoria->getContent()),
            '«Limpiar» también debe aparecer con solo el filtro de categoría'
        );
    }
}
