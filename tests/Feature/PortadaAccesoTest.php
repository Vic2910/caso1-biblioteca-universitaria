<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cubre los escenarios V-007 y V-008 de
 * specs/005-gestion-portadas/quickstart.md (FR-007, FR-008, SC-006,
 * SC-007): `GET /portadas/{id}` responde 401 sin sesión —nunca con los
 * bytes de la imagen— y sirve la imagen con su tipo MIME derivado con
 * sesión; los 404 en español; y `alt` no vacío en el 100 % de las
 * imágenes (título del libro / «Sin portada»).
 */
class PortadaAccesoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marcador de contenido que jamás debe aparecer en una respuesta 401.
     */
    private const CONTENIDO_IMAGEN = 'CONTENIDO-IMAGEN-PORTADA-005';

    private const MENSAJE_SESION = 'Debe iniciar sesión para ver las imágenes de portadas.';

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
    private function libroConPortada(array $atributos = [], string $extension = 'jpg'): array
    {
        $libro = Libro::factory()->create($atributos);
        $ruta = "portadas/{$libro->id}/portada.{$extension}";

        Storage::disk('public')->put($ruta, self::CONTENIDO_IMAGEN);

        $libro->portada = $ruta;
        $libro->save();

        return [$libro, $ruta];
    }

    /**
     * Extrae todas las imágenes de portada del HTML con sus atributos.
     *
     * @return list<array{src: string, alt: string, onerror: string}>
     */
    private function imagenesDe(string $html): array
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $consulta = '//img[contains(concat(" ", normalize-space(@class), " "), " portada ")]';
        $imagenes = [];

        foreach ($xpath->query($consulta) as $imagen) {
            $imagenes[] = [
                'src' => $imagen->getAttribute('src'),
                'alt' => $imagen->getAttribute('alt'),
                'onerror' => $imagen->getAttribute('onerror'),
            ];
        }

        return $imagenes;
    }

    public function test_sin_sesion_responde_401_con_el_mensaje_en_espanol_y_sin_los_bytes_de_la_imagen(): void
    {
        [$libro] = $this->libroConPortada(['titulo' => 'Cien años de soledad']);

        $respuesta = $this->get(route('portadas.show', $libro));

        $respuesta->assertStatus(401);
        $respuesta->assertSee(self::MENSAJE_SESION);
        $respuesta->assertDontSee(self::CONTENIDO_IMAGEN, false, 'Nunca se sirven los bytes de la imagen');
        $respuesta->assertDontSee((string) $libro->portada, false, 'No se expone la ruta interna del archivo');
    }

    public function test_con_sesion_se_sirven_los_bytes_con_el_tipo_mime_derivado(): void
    {
        $this->actingAs(User::factory()->create());

        [$libroJpg] = $this->libroConPortada(['titulo' => 'Rayuela'], 'jpg');
        [$libroPng] = $this->libroConPortada(['titulo' => 'La casa de los espíritus'], 'png');

        $respuestaJpg = $this->get(route('portadas.show', $libroJpg));
        $respuestaJpg->assertOk();
        $this->assertSame(self::CONTENIDO_IMAGEN, $respuestaJpg->streamedContent());
        $this->assertSame('image/jpeg', $respuestaJpg->headers->get('Content-Type'));

        $respuestaPng = $this->get(route('portadas.show', $libroPng));
        $respuestaPng->assertOk();
        $this->assertSame('image/png', $respuestaPng->headers->get('Content-Type'));
    }

    public function test_un_libro_inexistente_responde_404_en_espanol(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/portadas/999999')
            ->assertNotFound()
            ->assertSee('Libro no encontrado');
    }

    public function test_un_libro_sin_portada_responde_404(): void
    {
        $this->actingAs(User::factory()->create());

        $libro = Libro::factory()->create(['portada' => null]);

        $this->get(route('portadas.show', $libro))
            ->assertNotFound()
            ->assertSee('Portada no encontrada');
    }

    public function test_un_archivo_ausente_en_disco_responde_404_sin_imagen(): void
    {
        $this->actingAs(User::factory()->create());

        $libro = Libro::factory()->create(['portada' => 'portadas/perdida.jpg']);

        $respuesta = $this->get(route('portadas.show', $libro));

        $respuesta->assertNotFound();
        $respuesta->assertDontSee(self::CONTENIDO_IMAGEN, false);
    }

    public function test_el_listado_y_la_ficha_usan_el_titulo_como_alt_y_la_predeterminada_con_texto_generico(): void
    {
        [$libroConPortada] = $this->libroConPortada(['titulo' => 'Rayuela']);
        $libroSinPortada = Libro::factory()->create(['titulo' => 'Libro sin cubrir', 'portada' => null]);
        $predeterminada = asset('images/libro-sin-portada.svg');

        // Listado: el 100 % de las portadas reales usa el título y la
        // predeterminada el texto genérico (SC-007).
        $imagenes = $this->imagenesDe($this->get(route('catalogo.index'))->assertOk()->getContent());
        $this->assertCount(2, $imagenes, 'El listado debe mostrar las dos imágenes de portada');

        foreach ($imagenes as $imagen) {
            $this->assertNotSame('', $imagen['alt'], 'Ninguna imagen puede tener alt vacío');

            if (str_contains($imagen['src'], '/portadas/')) {
                $this->assertSame('Rayuela', $imagen['alt'], 'El alt de una portada real es el título del libro');
            } else {
                $this->assertSame($predeterminada, $imagen['src']);
                $this->assertSame('Sin portada', $imagen['alt']);
            }
        }

        // Ficha con portada: alt = título.
        $imagenes = $this->imagenesDe($this->get(route('catalogo.show', $libroConPortada))->assertOk()->getContent());
        $this->assertCount(1, $imagenes);
        $this->assertSame('Rayuela', $imagenes[0]['alt']);
        $this->assertStringContainsString('/portadas/', $imagenes[0]['src']);

        // Ficha sin portada: alt genérico.
        $imagenes = $this->imagenesDe($this->get(route('catalogo.show', $libroSinPortada))->assertOk()->getContent());
        $this->assertCount(1, $imagenes);
        $this->assertSame('Sin portada', $imagenes[0]['alt']);
        $this->assertSame($predeterminada, $imagenes[0]['src']);
    }

    public function test_sin_sesion_el_catalogo_muestra_el_respaldo_predeterminado_y_no_un_icono_roto(): void
    {
        [$libro] = $this->libroConPortada();

        $imagenes = $this->imagenesDe($this->get(route('catalogo.index'))->assertOk()->getContent());

        $portada = null;
        foreach ($imagenes as $imagen) {
            if (str_contains($imagen['src'], '/portadas/')) {
                $portada = $imagen;
            }
        }

        $this->assertNotNull($portada, 'El listado debe incluir la portada registrada');
        $this->assertStringContainsString(
            asset('images/libro-sin-portada.svg'),
            $portada['onerror'],
            'Sin sesión el onerror sustituye la imagen por la predeterminada'
        );
        $this->assertStringContainsString('this.onerror=null', $portada['onerror']);
        $this->assertStringContainsString(route('portadas.show', $libro), $portada['src']);
    }
}
