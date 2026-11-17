<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cubre los escenarios V-004 y V-005 de
 * specs/005-gestion-portadas/quickstart.md (FR-005, SC-002, SC-004,
 * SC-005): la eliminación de portada solo existe como
 * `DELETE /libros/{id}/portada` con confirmación previa en el formulario
 * de edición, los 404 en español («Portada no encontrada» / «Libro no
 * encontrado») y el borrado sin huérfanos del directorio `portadas/{id}/`
 * al eliminar el libro (D-020/T5).
 */
class PortadaEliminacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Texto literal del `confirm()` nativo (contracts/user-messages.md §3).
     */
    private const CONFIRMACION = "confirm('¿Seguro que desea eliminar la portada de este libro? La imagen dejará de mostrarse.')";

    private const MENSAJE_EXITO = 'Portada eliminada correctamente.';

    /**
     * Aísla el disco público en cada prueba (research D-026).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Crea un libro con su archivo de portada en disco y la referencia en
     * la base de datos.
     *
     * @return array{Libro, string}
     */
    private function libroConPortada(array $atributos = []): array
    {
        $libro = Libro::factory()->create($atributos);
        $ruta = "portadas/{$libro->id}/portada.jpg";

        Storage::disk('public')->put($ruta, 'CONTENIDO-IMAGEN-PORTADA-005');

        $libro->portada = $ruta;
        $libro->save();

        return [$libro, $ruta];
    }

    /**
     * Devuelve el elemento <form> con el id indicado o `null` si no existe.
     */
    private function formularioPorId(string $html, string $id): ?DOMElement
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $nodos = (new DOMXPath($documento))->query(sprintf('//*[@id="%s"]', $id));
        $elemento = $nodos->length > 0 ? $nodos->item(0) : null;

        return $elemento instanceof DOMElement ? $elemento : null;
    }

    /**
     * Devuelve el botón enlazado al formulario de borrado mediante el
     * atributo HTML5 `form` o `null` si no existe.
     */
    private function botonDeEliminacion(string $html): ?DOMElement
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $nodos = (new DOMXPath($documento))->query('//button[@form="formulario-eliminar-portada"]');
        $elemento = $nodos->length > 0 ? $nodos->item(0) : null;

        return $elemento instanceof DOMElement ? $elemento : null;
    }

    public function test_existe_la_ruta_nombrada_para_eliminar_la_portada(): void
    {
        $this->assertTrue(Route::has('libros.portadas.destroy'));
    }

    public function test_confirmar_la_eliminacion_borra_el_archivo_la_referencia_y_muestra_el_mensaje(): void
    {
        [$libro, $ruta] = $this->libroConPortada(['titulo' => 'Rayuela']);
        $disco = Storage::disk('public');

        $respuesta = $this->delete('/libros/'.$libro->id.'/portada');

        $respuesta->assertRedirect(route('libros.edit', $libro));
        $this->assertSame(self::MENSAJE_EXITO, session('exito'));

        $disco->assertMissing($ruta);
        $disco->assertCount("portadas/{$libro->id}", 0, 'No debe quedar ningún archivo huérfano');

        $libro->refresh();
        $this->assertNull($libro->portada, 'La referencia en la base de datos debe quedar en NULL');

        // V-004: el mensaje de éxito se muestra junto al formulario.
        $this->get(route('libros.edit', $libro))
            ->assertOk()
            ->assertSee(self::MENSAJE_EXITO);
    }

    public function test_tras_eliminar_la_portada_el_listado_y_la_ficha_muestran_la_predeterminada(): void
    {
        [$libro] = $this->libroConPortada(['titulo' => 'Rayuela']);
        $this->delete('/libros/'.$libro->id.'/portada');

        $predeterminada = asset('images/libro-sin-portada.svg');

        foreach ([route('catalogo.index'), route('catalogo.show', $libro)] as $url) {
            $pagina = $this->get($url)->assertOk();

            $pagina->assertDontSee('/portadas/'.$libro->id, false);
            $pagina->assertSee($predeterminada, false);
            $pagina->assertSee('alt="Sin portada"', false);
        }
    }

    public function test_un_get_a_la_ruta_de_borrado_no_elimina_nada_y_responde_405(): void
    {
        [$libro, $ruta] = $this->libroConPortada();
        $disco = Storage::disk('public');

        $respuesta = $this->get('/libros/'.$libro->id.'/portada');

        $respuesta->assertStatus(405);
        $disco->assertExists($ruta);
        $this->assertSame($ruta, $libro->refresh()->portada, 'GET no debe eliminar nada (SC-004)');
    }

    public function test_post_put_y_patch_no_estan_permitidos_en_la_ruta_de_borrado(): void
    {
        [$libro, $ruta] = $this->libroConPortada();
        $disco = Storage::disk('public');

        $this->post('/libros/'.$libro->id.'/portada')->assertStatus(405);
        $this->put('/libros/'.$libro->id.'/portada')->assertStatus(405);
        $this->patch('/libros/'.$libro->id.'/portada')->assertStatus(405);

        $disco->assertExists($ruta);
        $this->assertSame($ruta, $libro->refresh()->portada);
    }

    public function test_eliminar_la_portada_de_un_libro_sin_imagen_responde_404_en_espanol(): void
    {
        $libro = Libro::factory()->create(['portada' => null]);

        $respuesta = $this->delete('/libros/'.$libro->id.'/portada');

        $respuesta->assertNotFound();
        $respuesta->assertSee('Portada no encontrada');
        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'portada' => null]);
    }

    public function test_eliminar_la_portada_de_un_libro_inexistente_responde_404_en_espanol(): void
    {
        $this->delete('/libros/99999/portada')
            ->assertNotFound()
            ->assertSee('Libro no encontrado');
    }

    public function test_el_formulario_de_edicion_muestra_el_boton_de_eliminar_con_confirmacion(): void
    {
        [$libro] = $this->libroConPortada(['titulo' => 'Rayuela']);

        $html = $this->get(route('libros.edit', $libro))->assertOk()->getContent();

        $formulario = $this->formularioPorId($html, 'formulario-eliminar-portada');
        $this->assertNotNull($formulario, 'La edición debe incluir el formulario propio de borrado');

        $this->assertSame('post', $formulario->getAttribute('method'));
        $this->assertSame(url('/libros/'.$libro->id.'/portada'), $formulario->getAttribute('action'));
        $this->assertStringContainsString(
            self::CONFIRMACION,
            $formulario->getAttribute('onsubmit'),
            'El formulario debe pedir confirmación con el texto literal del contrato §3'
        );

        $campos = [];
        foreach ($formulario->getElementsByTagName('input') as $entrada) {
            $campos[$entrada->getAttribute('name')] = $entrada->getAttribute('value');
        }
        $this->assertSame('DELETE', $campos['_method'] ?? null, 'El borrado se envía como método real DELETE');
        $this->assertArrayHasKey('_token', $campos, 'El formulario propio debe llevar @csrf');

        $boton = $this->botonDeEliminacion($html);
        $this->assertNotNull($boton, 'El botón «Eliminar portada» debe enlazar al formulario con el atributo form');
        $this->assertSame('submit', $boton->getAttribute('type'));
        $this->assertSame('Eliminar portada', trim($boton->textContent));
    }

    public function test_sin_portada_la_edicion_no_muestra_controles_de_eliminacion(): void
    {
        $libro = Libro::factory()->create(['portada' => null]);

        $pagina = $this->get(route('libros.edit', $libro))->assertOk();
        $html = $pagina->getContent();

        $this->assertNull($this->formularioPorId($html, 'formulario-eliminar-portada'));
        $this->assertNull($this->botonDeEliminacion($html));
        $pagina->assertDontSee('Eliminar portada');
    }

    public function test_eliminar_el_libro_retira_el_directorio_de_portadas_completo(): void
    {
        [$libro, $ruta] = $this->libroConPortada();
        $disco = Storage::disk('public');
        $disco->put("portadas/{$libro->id}/temporal.jpg", 'CONTENIDO-TEMPORAL');

        $respuesta = $this->delete('/libros/'.$libro->id);

        $respuesta->assertRedirect(route('catalogo.index'));
        $this->assertDatabaseMissing('libros', ['id' => $libro->id]);
        $disco->assertMissing($ruta);
        $disco->assertMissing("portadas/{$libro->id}/temporal.jpg");
        $disco->assertCount("portadas/{$libro->id}", 0, 'V-005: no debe quedar ningún archivo huérfano');
    }

    public function test_eliminar_el_libro_borra_tambien_una_portada_fuera_del_directorio(): void
    {
        // Portadas dadas de alta en `003` con `store('portadas')`: guardadas
        // en la raíz del disco, fuera de `portadas/{id}/` (T020).
        $libro = Libro::factory()->create(['portada' => 'portadas/legada.jpg']);
        $disco = Storage::disk('public');
        $disco->put('portadas/legada.jpg', 'CONTENIDO-LEGADO-003');

        $respuesta = $this->delete('/libros/'.$libro->id);

        $respuesta->assertRedirect(route('catalogo.index'));
        $this->assertDatabaseMissing('libros', ['id' => $libro->id]);
        $disco->assertMissing('portadas/legada.jpg');
    }
}
