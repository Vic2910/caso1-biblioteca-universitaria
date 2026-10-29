<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los escenarios V-001, V-003 y V-004 de specs/003-gestion-libros/quickstart.md
 * (FR-003, FR-004, FR-006, SC-002, SC-004, SC-006): alta válida con disponibilidad
 * derivada, y rechazos con mensaje en español junto al campo conservando lo escrito.
 */
class LibroCreacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Datos válidos de alta sobre una categoría existente.
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
     * Comprueba que el mensaje de error aparece en el mismo bloque que el campo,
     * es decir, inmediatamente debajo del input correspondiente (SC-004).
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

    public function test_el_alta_valida_redirige_a_la_ficha_con_mensaje_y_disponibles_igual_al_total(): void
    {
        $categoria = Categoria::factory()->create();

        $respuesta = $this->post('/libros', $this->datosValidos($categoria));

        $libro = Libro::query()->firstWhere('isbn', '9788437604947');

        $this->assertNotNull($libro, 'El libro debe haberse creado');
        $respuesta->assertRedirect(route('catalogo.show', $libro));
        $respuesta->assertSessionHas('success', 'Libro registrado correctamente.');
        $this->assertSame($libro->ejemplares_totales, $libro->ejemplares_disponibles);
    }

    public function test_la_ficha_del_libro_creado_es_alcanzable_y_muestra_sus_datos(): void
    {
        $this->post('/libros', $this->datosValidos());

        $libro = Libro::query()->firstWhere('isbn', '9788437604947');

        $this->get(route('catalogo.show', $libro))
            ->assertOk()
            ->assertSee('Cien años de soledad')
            ->assertSee('9788437604947');
    }

    public function test_el_alta_ignora_cualquier_ejemplares_disponibles_enviado(): void
    {
        $datos = $this->datosValidos();
        $datos['ejemplares_disponibles'] = 999;

        $this->post('/libros', $datos)->assertSessionHasNoErrors();

        $libro = Libro::query()->firstWhere('isbn', '9788437604947');

        $this->assertSame(3, $libro->ejemplares_disponibles, 'El formulario no acepta ejemplares_disponibles');
    }

    public function test_el_isbn_duplicado_se_rechaza_en_espanol_junto_al_campo(): void
    {
        Libro::factory()->create(['isbn' => '9788437604947']);

        $respuesta = $this
            ->from(route('libros.create'))
            ->post('/libros', $this->datosValidos());

        $mensaje = 'El ISBN ya está registrado en otro libro.';
        $respuesta->assertRedirect(route('libros.create'));
        $respuesta->assertSessionHasErrors(['isbn' => $mensaje]);
        $this->assertDatabaseCount('libros', 1);
        $this->assertErrorJuntoAlCampo(
            $this->followingRedirects()->from(route('libros.create'))->post('/libros', $this->datosValidos())->getContent(),
            'isbn',
            $mensaje
        );
    }

    public function test_un_isbn_con_formato_invalido_se_rechaza_en_espanol(): void
    {
        $mensaje = 'El ISBN debe tener exactamente 13 dígitos, sin guiones ni espacios.';

        foreach (['123', 'abcdefghijklm', '978-3-16-148410-0'] as $isbn) {
            $respuesta = $this->from(route('libros.create'))->post('/libros', array_merge($this->datosValidos(), [
                'isbn' => $isbn,
            ]));

            $respuesta->assertSessionHasErrors(['isbn' => $mensaje]);
            $this->assertDatabaseCount('libros', 0);
        }
    }

    public function test_el_anio_fuera_de_rango_se_rechaza_y_los_limites_validos_se_aceptan(): void
    {
        $mensaje = 'El año de publicación debe estar entre 1900 y '.now()->year.'.';
        $anoActual = (int) now()->year;

        foreach ([1899, $anoActual + 1] as $anio) {
            $respuesta = $this->from(route('libros.create'))->post('/libros', array_merge($this->datosValidos(), [
                'anio_publicacion' => $anio,
            ]));

            $respuesta->assertSessionHasErrors(['anio_publicacion' => $mensaje]);
            $this->assertDatabaseCount('libros', 0);
        }

        $datosBase = $this->datosValidos();

        foreach ([1900, $anoActual] as $indice => $anio) {
            $respuesta = $this->post('/libros', array_merge($datosBase, [
                'isbn' => '978'.str_pad((string) $indice, 10, '0', STR_PAD_LEFT),
                'anio_publicacion' => $anio,
            ]));

            $respuesta->assertSessionHasNoErrors();
            $this->assertDatabaseCount('libros', $indice + 1);
        }
    }

    public function test_el_formulario_vacio_rechaza_cada_campo_obligatorio_en_espanol(): void
    {
        $respuesta = $this->from(route('libros.create'))->post('/libros', []);

        $respuesta->assertRedirect(route('libros.create'));
        $respuesta->assertSessionHasErrors([
            'isbn' => 'El campo ISBN es obligatorio.',
            'titulo' => 'El campo Título es obligatorio.',
            'autor' => 'El campo Autor es obligatorio.',
            'editorial' => 'El campo Editorial es obligatorio.',
            'anio_publicacion' => 'El campo Año de publicación es obligatorio.',
            'ejemplares_totales' => 'El campo Número de ejemplares debe ser un número entero mayor o igual que 1.',
            'categoria_id' => 'Debe seleccionar una categoría válida.',
        ]);
        $this->assertDatabaseCount('libros', 0);
    }

    public function test_titulo_y_autor_con_menos_de_dos_caracteres_se_rechazan(): void
    {
        $mensajes = [
            'titulo' => 'El campo Título debe tener al menos 2 caracteres.',
            'autor' => 'El campo Autor debe tener al menos 2 caracteres.',
        ];

        foreach ($mensajes as $campo => $mensaje) {
            $respuesta = $this->from(route('libros.create'))->post('/libros', array_merge($this->datosValidos(), [
                $campo => 'A',
            ]));

            $respuesta->assertSessionHasErrors([$campo => $mensaje]);
            $this->assertDatabaseCount('libros', 0);
        }
    }

    public function test_una_categoria_inexistente_se_rechaza_en_espanol(): void
    {
        $respuesta = $this->from(route('libros.create'))->post('/libros', array_merge($this->datosValidos(), [
            'categoria_id' => 999999,
        ]));

        $respuesta->assertSessionHasErrors(['categoria_id' => 'Debe seleccionar una categoría válida.']);
        $this->assertDatabaseCount('libros', 0);
    }
}
