<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cubre los escenarios V-001, V-002 y V-003 de
 * specs/005-gestion-portadas/quickstart.md (FR-001, FR-002, FR-006,
 * SC-001/SC-003): asociar y reemplazar la portada desde la edición de
 * libros, formatos y límite de 2 MB inclusivo con los mensajes exactos
 * junto al campo, rechazos que conservan `old()` y la portada previa, y
 * edición de otros datos sin archivo (transición T4).
 */
class PortadaSubidaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cabeceras mágicas de los formatos con los que se fabrican fixtures.
     */
    private const CABECERA_JPG = "\xFF\xD8\xFF\xE0";

    private const CABECERA_PNG = "\x89PNG\r\n\x1a\n"."\x00\x00\x00\x0dIHDR"."\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00"."\x00\x00\x00\x00";

    private const CABECERA_WEBP = "RIFF\x00\x00\x00\x00WEBPVP8 ";

    private const CABECERA_GIF = 'GIF89a';

    private const CABECERA_TIFF = "II\x2a\x00";

    /**
     * Textos literales de contracts/user-messages.md (§1, §2 y §6).
     */
    private const MENSAJE_FORMATO = 'La portada debe ser una imagen JPG, PNG o WebP.';

    private const MENSAJE_TAMANO = 'La portada no puede superar los 2 MB.';

    private const MENSAJE_EXITO = 'Portada actualizada correctamente.';

    /**
     * Aísla el disco público en cada prueba (research D-026).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Libro de ejemplo sin portada (fábrica de `001`).
     */
    private function libroDeEjemplo(array $atributos = []): Libro
    {
        return Libro::factory()->create($atributos);
    }

    /**
     * Datos válidos de edición partiendo del libro en cuestión.
     *
     * @return array<string, mixed>
     */
    private function datosValidos(Libro $libro, array $sobrescritura = []): array
    {
        return array_merge([
            'isbn' => $libro->isbn,
            'titulo' => $libro->titulo,
            'autor' => $libro->autor,
            'editorial' => $libro->editorial,
            'anio_publicacion' => $libro->anio_publicacion,
            'ejemplares_totales' => $libro->ejemplares_totales,
            'categoria_id' => $libro->categoria_id,
        ], $sobrescritura);
    }

    /**
     * Crea una imagen temporal real cuyo contenido empieza por la cabecera
     * indicada y ocupa exactamente el número de kilobytes dado.
     */
    private function archivoImagen(string $nombre, string $cabecera, int $kilobytes): UploadedFile
    {
        $total = $kilobytes * 1024;
        $ruta = tempnam(sys_get_temp_dir(), 'portada');
        file_put_contents($ruta, $cabecera.str_repeat("\0", $total - strlen($cabecera)));

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    /**
     * Siembra una portada inicial en disco y en la base de datos.
     */
    private function sembrarPortada(Libro $libro): string
    {
        $ruta = "portadas/{$libro->id}/portada-inicial.jpg";

        Storage::disk('public')->put($ruta, self::CABECERA_JPG.str_repeat("\0", 1024));

        $libro->portada = $ruta;
        $libro->save();

        return $ruta;
    }

    /**
     * Comprueba que el mensaje aparece en el mismo bloque que el campo
     * (contrato ui-structure.md §1, «junto al campo»).
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

    /**
     * @return list<array{string, string}>
     */
    public static function formatosRechazados(): array
    {
        $svg = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';

        return [
            ['portada.gif', self::CABECERA_GIF],
            ['portada.svg', $svg],
            ['portada.tiff', self::CABECERA_TIFF],
        ];
    }

    public function test_los_tres_formatos_admitidos_asocian_la_portada_en_su_directorio_y_se_muestran_en_listado_y_ficha(): void
    {
        foreach (self::formatosAdmitidos() as [$nombre, $cabecera]) {
            $libro = $this->libroDeEjemplo();
            $datos = $this->datosValidos($libro, ['portada' => $this->archivoImagen($nombre, $cabecera, 512)]);

            $respuesta = $this->from(route('libros.edit', $libro))
                ->put('/libros/'.$libro->id, $datos);

            $respuesta->assertRedirect(route('libros.edit', $libro));
            $respuesta->assertSessionHasNoErrors();
            $respuesta->assertSessionHas('exito', self::MENSAJE_EXITO);

            $libro->refresh();
            $this->assertNotNull($libro->portada, 'La ruta de la portada debe guardarse en la BD');
            $this->assertStringStartsWith("portadas/{$libro->id}/", $libro->portada, 'La ruta debe ser relativa y propia del libro');
            $this->assertFalse(str_starts_with($libro->portada, '/'), 'La ruta no debe ser absoluta');

            $disco = Storage::disk('public');
            $disco->assertExists($libro->portada);
            $disco->assertCount("portadas/{$libro->id}", 1, 'Debe haber exactamente un archivo por libro');

            // El mensaje de éxito se muestra junto al formulario de edición.
            $this->get(route('libros.edit', $libro))
                ->assertOk()
                ->assertSee(self::MENSAJE_EXITO);

            $this->get(route('catalogo.index'))
                ->assertOk()
                ->assertSee(route('portadas.show', $libro), false);

            $this->get(route('catalogo.show', $libro))
                ->assertOk()
                ->assertSee(route('portadas.show', $libro), false);
        }
    }

    public function test_al_reemplazar_la_portada_el_archivo_anterior_deja_de_existir(): void
    {
        $libro = $this->libroDeEjemplo();
        $datos = $this->datosValidos($libro, ['portada' => $this->archivoImagen('primera.jpg', self::CABECERA_JPG, 256)]);
        $this->from(route('libros.edit', $libro))->put('/libros/'.$libro->id, $datos)
            ->assertSessionHasNoErrors();

        $rutaAnterior = $libro->fresh()->portada;
        $this->assertNotNull($rutaAnterior);

        $datos = $this->datosValidos($libro, ['portada' => $this->archivoImagen('segunda.png', self::CABECERA_PNG, 300)]);
        $this->from(route('libros.edit', $libro))->put('/libros/'.$libro->id, $datos)
            ->assertSessionHas('exito', self::MENSAJE_EXITO);

        $libro->refresh();
        $this->assertNotSame($rutaAnterior, $libro->portada, 'La referencia debe apuntar al archivo nuevo');

        $disco = Storage::disk('public');
        $disco->assertMissing($rutaAnterior);
        $disco->assertExists($libro->portada);
        $disco->assertCount("portadas/{$libro->id}", 1, 'Tras el reemplazo solo debe quedar el archivo nuevo');
    }

    public function test_un_archivo_de_exactamente_dos_mb_se_acepta(): void
    {
        $libro = $this->libroDeEjemplo();
        $datos = $this->datosValidos($libro, [
            'portada' => $this->archivoImagen('limite.jpg', self::CABECERA_JPG, 2048),
        ]);

        $respuesta = $this->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $datos);

        $respuesta->assertSessionHasNoErrors('El límite de 2 MB es inclusivo (2048 KB exactos)');
        $respuesta->assertSessionHas('exito', self::MENSAJE_EXITO);

        $libro->refresh();
        $this->assertStringStartsWith("portadas/{$libro->id}/", (string) $libro->portada);
        Storage::disk('public')->assertExists((string) $libro->portada);
    }

    public function test_un_png_de_tres_mb_se_rechaza_con_mensaje_junto_al_campo(): void
    {
        $libro = $this->libroDeEjemplo();
        $ruta = $this->sembrarPortada($libro);

        $datos = $this->datosValidos($libro, [
            'portada' => $this->archivoImagen('grande.png', self::CABECERA_PNG, 3000),
        ]);

        $respuesta = $this->followingRedirects()
            ->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $datos);

        $respuesta->assertOk();
        $this->assertErrorJuntoAlCampo($respuesta->getContent(), 'portada', self::MENSAJE_TAMANO);

        $libro->refresh();
        $this->assertSame($ruta, $libro->portada, 'La portada previa debe quedar intacta');
        Storage::disk('public')->assertCount("portadas/{$libro->id}", 1);
    }

    public function test_los_formatos_gif_svg_y_tiff_se_rechazan_con_el_mensaje_de_formato(): void
    {
        foreach (self::formatosRechazados() as [$nombre, $contenido]) {
            $libro = $this->libroDeEjemplo();
            $ruta = $this->sembrarPortada($libro);

            $datos = $this->datosValidos($libro, [
                'portada' => $this->archivoImagen($nombre, $contenido, 16),
            ]);

            $respuesta = $this->followingRedirects()
                ->from(route('libros.edit', $libro))
                ->put('/libros/'.$libro->id, $datos);

            $respuesta->assertOk();
            $this->assertErrorJuntoAlCampo($respuesta->getContent(), 'portada', self::MENSAJE_FORMATO);

            $libro->refresh();
            $this->assertSame($ruta, $libro->portada, "La portada previa debe quedar intacta tras rechazar {$nombre}");

            $disco = Storage::disk('public');
            $disco->assertExists($ruta);
            $disco->assertCount("portadas/{$libro->id}", 1, 'El rechazo no debe añadir archivos');
        }
    }

    public function test_un_jpg_cuyo_contenido_no_es_imagen_se_rechaza(): void
    {
        $libro = $this->libroDeEjemplo();
        $ruta = $this->sembrarPortada($libro);

        $datos = $this->datosValidos($libro);

        // UploadedFile real: el nombre .jpg con contenido de texto debe
        // rechazarse por análisis de contenido, no por nombre de archivo.
        $rutaTexto = tempnam(sys_get_temp_dir(), 'portada');
        file_put_contents($rutaTexto, 'esto no es una imagen sino un archivo de texto plano');
        $datos['portada'] = new UploadedFile($rutaTexto, 'portada.jpg', null, null, true);

        $respuesta = $this->followingRedirects()
            ->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $datos);

        $respuesta->assertOk();
        $this->assertErrorJuntoAlCampo($respuesta->getContent(), 'portada', self::MENSAJE_FORMATO);

        $libro->refresh();
        $this->assertSame($ruta, $libro->portada);
        Storage::disk('public')->assertCount("portadas/{$libro->id}", 1);
    }

    public function test_el_rechazo_conserva_los_valores_del_formulario_y_la_portada_previa(): void
    {
        $libro = $this->libroDeEjemplo();
        $ruta = $this->sembrarPortada($libro);

        $datos = $this->datosValidos($libro, [
            'titulo' => 'Título enviado con error',
            'portada' => $this->archivoImagen('portada.gif', self::CABECERA_GIF, 16),
        ]);

        $respuesta = $this->followingRedirects()
            ->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $datos);

        $respuesta->assertOk();
        $this->assertErrorJuntoAlCampo($respuesta->getContent(), 'portada', self::MENSAJE_FORMATO);

        // El resto de valores se conserva con old() y la BD no cambia.
        $respuesta->assertSee('value="Título enviado con error"', false);
        $respuesta->assertSee('value="'.$libro->isbn.'"', false);

        $libro->refresh();
        $this->assertNotSame('Título enviado con error', $libro->titulo, 'El libro no debe actualizarse tras un rechazo');
        $this->assertSame($ruta, $libro->portada, 'La portada existente debe quedar intacta tras el rechazo');

        $disco = Storage::disk('public');
        $disco->assertExists($ruta);
        $disco->assertCount("portadas/{$libro->id}", 1);
    }

    public function test_editar_otros_datos_sin_archivo_no_toca_la_portada_ni_el_disco(): void
    {
        $libro = $this->libroDeEjemplo();
        $ruta = $this->sembrarPortada($libro);

        $datos = $this->datosValidos($libro, ['titulo' => 'Rayuela revisada']);

        $respuesta = $this->put('/libros/'.$libro->id, $datos);

        $respuesta->assertSessionHasNoErrors();
        $respuesta->assertSessionHas('success', 'Libro actualizado correctamente.');

        $libro->refresh();
        $this->assertSame('Rayuela revisada', $libro->titulo);
        $this->assertSame($ruta, $libro->portada, 'La transición T4 no debe tocar la portada');

        $disco = Storage::disk('public');
        $disco->assertExists($ruta);
        $disco->assertCount("portadas/{$libro->id}", 1, 'La transición T4 no debe tocar el disco');
    }
}
