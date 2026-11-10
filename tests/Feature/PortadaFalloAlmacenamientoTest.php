<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Cubre los casos de guardado del escenario V-009 de
 * specs/005-gestion-portadas/quickstart.md (FR-006, SC-003, D-025/D-026):
 * un fallo de disco al guardar o de la base de datos al persistir
 * responde 302 con el mensaje exacto junto al campo, deja la portada
 * previa y el registro intactos y no deja archivos huérfanos (el nuevo se
 * elimina como compensación). Los casos de borrado (T016) cubren un fallo
 * de disco al eliminar la portada: mismo mensaje literal y archivo +
 * referencia intactos (SC-003).
 */
class PortadaFalloAlmacenamientoTest extends TestCase
{
    use RefreshDatabase;

    private const CABECERA_JPG = "\xFF\xD8\xFF\xE0";

    /**
     * Texto literal de contracts/user-messages.md §2.
     */
    private const MENSAJE_ALMACENAMIENTO = 'No se pudo guardar la imagen. Inténtelo de nuevo.';

    /**
     * Aísla el disco público en cada prueba (research D-026).
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
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
     * Crea una imagen temporal real con cabecera JPG del tamaño indicado.
     */
    private function archivoImagen(string $nombre, int $kilobytes): UploadedFile
    {
        $total = $kilobytes * 1024;
        $ruta = tempnam(sys_get_temp_dir(), 'portada');
        file_put_contents($ruta, self::CABECERA_JPG.str_repeat("\0", $total - strlen(self::CABECERA_JPG)));

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
     * Devuelve los mensajes flasheados para `portada` en la sesión actual
     * sin usar `assertSessionHasErrors()`: ese helper arranca el almacén
     * de sesión fuera de la petición y, con `session.serialization = json`,
     * la petición siguiente vuelve a ejecutar `marshalErrorBag()` sobre la
     * `ViewErrorBag` ya convertida; su `foreach` no itera nada y la vacía,
     * por lo que el mensaje dejaría de mostrarse junto al campo en el GET
     * posterior (fr-004 / contrato ui-structure §1).
     *
     * @return list<string>
     */
    private function mensajesDePortadaEnSesion(): array
    {
        $errores = session('errors');

        if ($errores instanceof ViewErrorBag) {
            return $errores->getBag('default')->get('portada');
        }

        return $errores['default']['messages']['portada'] ?? [];
    }

    /**
     * Comprueba que el mensaje aparece en el mismo bloque que el campo.
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
     * Sustituye el disco `public` por un doble cuyo `putFileAs` falla
     * (fallo de almacenamiento, D-025) y devuelve la instancia falsa real
     * para asertar sobre ella después.
     */
    private function discoFalloEnGuardado(): FilesystemAdapter
    {
        $discoReal = Storage::disk('public');

        $discoFallo = Mockery::mock(FilesystemAdapter::class);
        $discoFallo->shouldReceive('putFileAs')->andReturnFalse();

        Storage::shouldReceive('disk')->with('public')->andReturn($discoFallo);

        return $discoReal;
    }

    /**
     * Sustituye el disco `public` por un doble cuyo `delete` devuelve
     * `false` (fallo de almacenamiento al borrar, D-025) y devuelve la
     * instancia falsa real para asertar sobre ella después.
     */
    private function discoFalloEnBorrado(): FilesystemAdapter
    {
        $discoReal = Storage::disk('public');

        $discoFallo = Mockery::mock(FilesystemAdapter::class);
        $discoFallo->shouldReceive('delete')->andReturnFalse();

        Storage::shouldReceive('disk')->with('public')->andReturn($discoFallo);

        return $discoReal;
    }

    public function test_un_fallo_de_disco_al_guardar_muestra_el_mensaje_y_no_altera_la_portada_previa(): void
    {
        $libro = Libro::factory()->create(['titulo' => 'Rayuela']);
        $ruta = $this->sembrarPortada($libro);
        $discoReal = $this->discoFalloEnGuardado();

        $respuesta = $this->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $this->datosValidos($libro, [
                'titulo' => 'Rayuela edición nueva',
                'portada' => $this->archivoImagen('nueva.jpg', 512),
            ]));

        $respuesta->assertRedirect(route('libros.edit', $libro));
        $this->assertSame(
            [self::MENSAJE_ALMACENAMIENTO],
            $this->mensajesDePortadaEnSesion(),
            'La sesión debe llevar el mensaje exacto del fallo de disco'
        );

        // Portada previa intacta en disco y en la BD; registro sin cambios.
        $libro->refresh();
        $this->assertSame($ruta, $libro->portada);
        $this->assertSame('Rayuela', $libro->titulo, 'El registro del libro debe quedar intacto');

        $discoReal->assertExists($ruta);
        $discoReal->assertCount("portadas/{$libro->id}", 1, 'No debe añadirse ningún archivo');

        // El mensaje exacto aparece junto al campo `portada`.
        $pagina = $this->get(route('libros.edit', $libro))->assertOk();
        $this->assertErrorJuntoAlCampo($pagina->getContent(), 'portada', self::MENSAJE_ALMACENAMIENTO);
    }

    public function test_un_fallo_de_base_de_datos_tras_guardar_el_archivo_elimina_el_archivo_nuevo(): void
    {
        $libro = Libro::factory()->create(['titulo' => 'Rayuela']);
        $ruta = $this->sembrarPortada($libro);
        $discoReal = Storage::disk('public');

        // Simula un fallo de la base de datos al persistir (D-025).
        Libro::saving(static function (): void {
            throw new RuntimeException('Fallo simulado interno de la base de datos');
        });

        $respuesta = $this->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $this->datosValidos($libro, [
                'titulo' => 'Rayuela edición nueva',
                'portada' => $this->archivoImagen('nueva.jpg', 512),
            ]));

        $respuesta->assertRedirect(route('libros.edit', $libro));
        $this->assertSame(
            [self::MENSAJE_ALMACENAMIENTO],
            $this->mensajesDePortadaEnSesion(),
            'La sesión debe llevar el mensaje exacto del fallo de base de datos'
        );

        // La BD no cambia y el archivo nuevo se elimina como compensación:
        // solo queda la portada previa (cero huérfanos).
        $libro->refresh();
        $this->assertSame($ruta, $libro->portada);
        $this->assertSame('Rayuela', $libro->titulo);

        $discoReal->assertExists($ruta);
        $discoReal->assertCount("portadas/{$libro->id}", 1, 'El archivo nuevo no debe quedar huérfano');

        // Mensaje junto al campo, sin rutas ni trazas técnicas.
        $pagina = $this->get(route('libros.edit', $libro))->assertOk();
        $this->assertErrorJuntoAlCampo($pagina->getContent(), 'portada', self::MENSAJE_ALMACENAMIENTO);
        $pagina->assertDontSee('Fallo simulado interno de la base de datos', false, 'No se exponen trazas técnicas');
    }

    public function test_un_fallo_de_disco_al_eliminar_muestra_el_mensaje_y_conserva_la_portada(): void
    {
        $libro = Libro::factory()->create(['titulo' => 'Rayuela']);
        $ruta = $this->sembrarPortada($libro);
        $discoReal = $this->discoFalloEnBorrado();

        $respuesta = $this->from(route('libros.edit', $libro))
            ->delete('/libros/'.$libro->id.'/portada');

        $respuesta->assertRedirect(route('libros.edit', $libro));
        $this->assertSame(
            [self::MENSAJE_ALMACENAMIENTO],
            $this->mensajesDePortadaEnSesion(),
            'La sesión debe llevar el mensaje exacto del fallo de disco'
        );
        $this->assertNull(session('exito'), 'Sin borrado no hay mensaje de éxito');

        // Archivo y referencia intactos: sin cambios ante fallo de disco (SC-003).
        $libro->refresh();
        $this->assertSame($ruta, $libro->portada);

        $discoReal->assertExists($ruta);
        $discoReal->assertCount("portadas/{$libro->id}", 1);

        // El mensaje exacto aparece junto al campo `portada` (contrato §2).
        $pagina = $this->get(route('libros.edit', $libro))->assertOk();
        $this->assertErrorJuntoAlCampo($pagina->getContent(), 'portada', self::MENSAJE_ALMACENAMIENTO);
    }
}
