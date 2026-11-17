<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los escenarios V-002 y V-005 de specs/003-gestion-libros/quickstart.md
 * (FR-004, FR-007, SC-005, SC-006): edición válida, conservación de la portada
 * sin archivo, cota inferior de ejemplares_totales y recálculo de disponibilidad.
 */
class LibroEdicionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un libro de ejemplo con su categoría.
     */
    private function libroDeEjemplo(array $atributos = []): Libro
    {
        return Libro::factory()->create(array_merge([
            'isbn' => '9788420532783',
            'titulo' => 'Rayuela',
            'autor' => 'Julio Cortázar',
            'editorial' => 'Alfaguara',
            'anio_publicacion' => 1963,
            'ejemplares_totales' => 3,
        ], $atributos));
    }

    /**
     * Datos válidos de edición (sin archivo).
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

    public function test_la_edicion_valida_actualiza_la_ficha_con_mensaje_de_exito(): void
    {
        $categoria = Categoria::factory()->create();
        $libro = $this->libroDeEjemplo(['categoria_id' => $categoria->id]);

        $respuesta = $this->put('/libros/'.$libro->id, $this->datosValidos($libro, [
            'titulo' => 'Rayuela ampliada',
            'anio_publicacion' => 1984,
        ]));

        $respuesta->assertRedirect(route('catalogo.show', $libro));
        $respuesta->assertSessionHas('success', 'Libro actualizado correctamente.');

        $this->get(route('catalogo.show', $libro))
            ->assertOk()
            ->assertSee('Rayuela ampliada')
            ->assertSee('1984');
        $this->assertSame('Rayuela ampliada', $libro->fresh()->titulo);
    }

    public function test_el_formulario_de_edicion_no_contiene_campo_ejemplares_disponibles(): void
    {
        $libro = $this->libroDeEjemplo();

        $respuesta = $this->get(route('libros.edit', $libro));

        $respuesta->assertOk();
        $respuesta->assertDontSee('name="ejemplares_disponibles"', false);
        $respuesta->assertSee('name="isbn"', false);
        $respuesta->assertSee($libro->isbn);
    }

    public function test_editar_sin_enviar_archivo_conserve_la_portada_existente(): void
    {
        $libro = $this->libroDeEjemplo(['portada' => 'portadas/existente.jpg']);

        $respuesta = $this->put('/libros/'.$libro->id, $this->datosValidos($libro, [
            'titulo' => 'Rayuela revisada',
        ]));

        $respuesta->assertSessionHasNoErrors();
        $this->assertSame('portadas/existente.jpg', $libro->fresh()->portada);
    }

    public function test_bajar_el_total_por_debajo_de_los_prestamos_activos_se_rechaza_junto_al_campo(): void
    {
        $libro = $this->libroDeEjemplo(['ejemplares_totales' => 3]);
        Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);
        Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);
        $libro->save();

        $respuesta = $this
            ->from(route('libros.edit', $libro))
            ->put('/libros/'.$libro->id, $this->datosValidos($libro, ['ejemplares_totales' => 1]));

        $mensaje = 'El número de ejemplares no puede ser inferior a los préstamos activos (2).';
        $respuesta->assertRedirect(route('libros.edit', $libro));
        $respuesta->assertSessionHasErrors(['ejemplares_totales' => $mensaje]);

        $libro->refresh();
        $this->assertSame(3, $libro->ejemplares_totales, 'El libro no debe cambiar tras el rechazo');
        $this->assertErrorJuntoAlCampo(
            $this->followingRedirects()
                ->from(route('libros.edit', $libro))
                ->put('/libros/'.$libro->id, $this->datosValidos($libro, ['ejemplares_totales' => 1]))
                ->assertOk()
                ->getContent(),
            'ejemplares_totales',
            $mensaje
        );
    }

    public function test_subir_el_total_valido_recalcula_los_disponibles(): void
    {
        $libro = $this->libroDeEjemplo(['ejemplares_totales' => 3]);
        Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);
        Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);
        $libro->save();

        $respuesta = $this->put('/libros/'.$libro->id, $this->datosValidos($libro, [
            'ejemplares_totales' => 5,
        ]));

        $respuesta->assertSessionHasNoErrors();

        $libro->refresh();
        $this->assertSame(5, $libro->ejemplares_totales);
        $this->assertSame(3, $libro->ejemplares_disponibles, 'Disponibles = totales − préstamos activos');
    }

    public function test_editar_conservando_el_propio_isbn_es_valido(): void
    {
        $libro = $this->libroDeEjemplo();

        $respuesta = $this->put('/libros/'.$libro->id, $this->datosValidos($libro, [
            'titulo' => 'Rayuela (edición aniversario)',
        ]));

        $respuesta->assertSessionHasNoErrors();
        $this->assertSame('Rayuela (edición aniversario)', $libro->fresh()->titulo);
    }

    public function test_un_libro_inexistente_responde_404_en_espanol(): void
    {
        $libro = $this->libroDeEjemplo();

        $this->get('/libros/99999/editar')
            ->assertNotFound()
            ->assertSee('Libro no encontrado');

        $this->put('/libros/99999', $this->datosValidos($libro))
            ->assertNotFound()
            ->assertSee('Libro no encontrado');
    }
}
