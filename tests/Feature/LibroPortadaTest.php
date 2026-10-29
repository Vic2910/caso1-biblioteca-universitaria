<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cubre el escenario V-008 de specs/003-gestion-libros/quickstart.md
 * (FR-006, SC-008): portadas JPG/PNG/WebP ≤ 2 MB guardadas en el disco
 * público, y rechazo en español junto al campo de formatos y tamaños no
 * admitidos sin perder el resto de valores introducidos.
 */
class LibroPortadaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cabeceras mágicas de los formatos admitidos.
     */
    private const CABECERA_JPG = "\xFF\xD8\xFF\xE0";

    private const CABECERA_PNG = "\x89PNG\r\n\x1a\n"."\x00\x00\x00\x0dIHDR"."\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00"."\x00\x00\x00\x00";

    private const CABECERA_WEBP = "RIFF\x00\x00\x00\x00WEBPVP8 ";

    /**
     * Datos válidos de alta con una categoría existente.
     *
     * @return array<string, mixed>
     */
    private function datosValidos(?Categoria $categoria = null): array
    {
        return [
            'isbn' => '9788437604947',
            'titulo' => 'Cien años de soledad',
            'autor' => 'Gabriel García Márquez',
            'editorial' => 'Planeta',
            'anio_publicacion' => 1967,
            'ejemplares_totales' => 3,
            'categoria_id' => ($categoria ?? Categoria::factory()->create())->id,
        ];
    }

    /**
     * Crea un archivo temporal real cuyo contenido empieza por la cabecera
     * mágica indicada y ocupa el número de kilobytes dado.
     */
    private function archivoImagen(string $nombre, string $cabecera, int $kilobytes): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'portada');
        file_put_contents($ruta, $cabecera.str_repeat("\0", $kilobytes * 1024));

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    /**
     * Comprueba que el mensaje aparece en el mismo bloque que el campo (SC-004).
     */
    private function assertErrorJuntoAlCampo(string $html, string $campo, string $mensaje): void
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $nodos = $xpath->query(sprintf('//*[@name="%s"]', $campo));
        $this->assertNotEmpty($nodos, "El formulario debe contener el campo {$campo}");

        $this->assertStringContainsString(
            $mensaje,
            $nodos->item(0)->parentNode->textContent,
            "El mensaje debe mostrarse junto al campo {$campo}"
        );
    }

    /**
     * @return list<array{string, string}>
     */
    public static function formatosAdmitidos(): array
    {
        return [
            ['portada.jpg', self::CABECERA_JPG],
            ['portada.png', self::CABECERA_PNG],
            ['portada.webp', self::CABECERA_WEBP],
        ];
    }

    public function test_las_portadas_jpg_png_y_webp_se_guardan_en_el_disco_publico_y_se_muestran_en_la_ficha(): void
    {
        Storage::fake('public');

        foreach (self::formatosAdmitidos() as $indice => [$nombre, $cabecera]) {
            $datos = array_merge($this->datosValidos(), [
                'isbn' => '97884376049'.str_pad((string) $indice, 2, '0', STR_PAD_LEFT),
            ]);
            $datos['portada'] = $this->archivoImagen($nombre, $cabecera, 512);

            $this->post('/libros', $datos)->assertSessionHasNoErrors();

            $libro = Libro::query()->latest('id')->firstOrFail();

            $this->assertNotNull($libro->portada, 'La ruta de la portada debe guardarse en la BD');
            $this->assertStringStartsWith('portadas/', $libro->portada);
            Storage::disk('public')->assertExists($libro->portada);

            $this->get(route('catalogo.show', $libro))
                ->assertOk()
                ->assertSee(Storage::disk('public')->url($libro->portada), false);
        }

        $this->assertDatabaseCount('libros', 3);
    }

    public function test_un_archivo_de_texto_se_rechaza_junto_al_campo_conservando_el_resto_de_valores(): void
    {
        $datos = $this->datosValidos();
        $datos['portada'] = UploadedFile::fake()->createWithContent(
            'portada.txt',
            'esto no es una imagen sino un archivo de texto plano'
        );

        $respuesta = $this->followingRedirects()
            ->from(route('libros.create'))
            ->post('/libros', $datos);

        $mensaje = 'La portada debe ser un archivo JPG, PNG o WebP.';
        $respuesta->assertOk();
        $this->assertDatabaseCount('libros', 0);
        $this->assertErrorJuntoAlCampo($respuesta->getContent(), 'portada', $mensaje);
        $respuesta->assertSee($mensaje);

        // El resto de valores introducidos se conserva (old()).
        $respuesta->assertSee('value="9788437604947"', false);
        $respuesta->assertSee('Cien años de soledad');
    }

    public function test_una_imagen_de_mas_de_dos_mb_se_rechaza_junto_al_campo(): void
    {
        $datos = $this->datosValidos();
        $datos['portada'] = $this->archivoImagen('portada-grande.jpg', self::CABECERA_JPG, 3000);

        $respuesta = $this->followingRedirects()
            ->from(route('libros.create'))
            ->post('/libros', $datos);

        $mensaje = 'La portada no debe superar los 2 MB.';
        $respuesta->assertOk();
        $this->assertDatabaseCount('libros', 0);
        $this->assertErrorJuntoAlCampo($respuesta->getContent(), 'portada', $mensaje);
        $respuesta->assertSee($mensaje);
        $respuesta->assertSee('value="9788437604947"', false);
    }
}
