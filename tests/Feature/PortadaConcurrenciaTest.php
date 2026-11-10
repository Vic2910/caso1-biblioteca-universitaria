<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cubre el escenario V-010 de specs/005-gestion-portadas/quickstart.md
 * (SC-005, transiciones T2/T6 de data-model.md): dos reemplazos
 * superpuestos del mismo libro dejan como única imagen la del último
 * `UPDATE` y `portadas/{id}` contiene exactamente un archivo tras cada
 * operación, sin huérfanos — también para el archivo del escritor
 * desplazado, que elimina la reconciliación final (D-020).
 */
class PortadaConcurrenciaTest extends TestCase
{
    use RefreshDatabase;

    private const CABECERA_JPG = "\xFF\xD8\xFF\xE0";

    private const CABECERA_PNG = "\x89PNG\r\n\x1a\n"."\x00\x00\x00\x0dIHDR"."\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00"."\x00\x00\x00\x00";

    private const CABECERA_WEBP = "RIFF\x00\x00\x00\x00WEBPVP8 ";

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
    private function datosValidos(Libro $libro, UploadedFile $portada): array
    {
        return [
            'isbn' => $libro->isbn,
            'titulo' => $libro->titulo,
            'autor' => $libro->autor,
            'editorial' => $libro->editorial,
            'anio_publicacion' => $libro->anio_publicacion,
            'ejemplares_totales' => $libro->ejemplares_totales,
            'categoria_id' => $libro->categoria_id,
            'portada' => $portada,
        ];
    }

    /**
     * Crea una imagen temporal real cuyo contenido empieza por la cabecera
     * indicada y ocupa el número de kilobytes dado.
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

    public function test_dos_reemplazos_secuenciales_dejan_un_solo_archivo_y_predomina_la_ultima_escritura(): void
    {
        $libro = Libro::factory()->create();
        $this->sembrarPortada($libro);
        $disco = Storage::disk('public');

        // Primer reemplazo (T2): la portada vigente pasa a ser la nueva.
        $this->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $this->datosValidos($libro, $this->archivoImagen('primera.png', self::CABECERA_PNG, 256)))
            ->assertSessionHasNoErrors();

        $primeraRuta = (string) $libro->fresh()->portada;
        $disco->assertCount("portadas/{$libro->id}", 1, 'Tras el primer reemplazo debe haber exactamente un archivo');

        // Segundo reemplazo (T2): predomina el último UPDATE.
        $this->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $this->datosValidos($libro, $this->archivoImagen('segunda.webp', self::CABECERA_WEBP, 300)))
            ->assertSessionHasNoErrors();

        $segundaRuta = (string) $libro->fresh()->portada;

        $this->assertNotSame($primeraRuta, $segundaRuta);
        $this->assertStringStartsWith("portadas/{$libro->id}/", $segundaRuta);
        $disco->assertMissing($primeraRuta, 'El archivo desplazado por el segundo UPDATE ya no existe');
        $disco->assertExists($segundaRuta);
        $disco->assertCount("portadas/{$libro->id}", 1, 'Tras el segundo reemplazo solo debe quedar un archivo');
    }

    public function test_un_escritor_desplazado_no_deja_huerfanos_tras_la_reconciliacion_final(): void
    {
        $libro = Libro::factory()->create();
        $rutaInicial = $this->sembrarPortada($libro);
        $disco = Storage::disk('public');

        /*
         * Emulación del solapamiento (T6): el primer escritor ya guardó su
         * archivo —su `UPDATE` aún no ha caído—, de modo que el segundo
         * escritor captura como «ruta antigua» la inicial y su petición se
         * solapa con la del primero.
         */
        $rutaEscritorDesplazado = "portadas/{$libro->id}/escritor-desplazado.png";
        $disco->put($rutaEscritorDesplazado, self::CABECERA_PNG.str_repeat("\0", 512));

        // Segundo escritor: guarda, actualiza la BD (último UPDATE) y
        // reconcilia; la reconciliación elimina el archivo huérfano.
        $this->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $this->datosValidos($libro, $this->archivoImagen('final.webp', self::CABECERA_WEBP, 256)))
            ->assertSessionHasNoErrors();

        $rutaFinal = (string) $libro->fresh()->portada;
        $this->assertStringStartsWith("portadas/{$libro->id}/", $rutaFinal);

        $disco->assertMissing($rutaInicial, 'La ruta capturada por el segundo escritor se elimina');
        $disco->assertMissing($rutaEscritorDesplazado, 'El archivo del escritor desplazado no debe quedar huérfano');
        $disco->assertExists($rutaFinal);
        $disco->assertCount("portadas/{$libro->id}", 1, 'Siempre debe quedar exactamente un archivo (SC-005)');
    }
}
