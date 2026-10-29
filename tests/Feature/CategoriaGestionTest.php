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
 * Cubre los escenarios V-009 y V-010 de specs/003-gestion-libros/quickstart.md
 * (FR-008, SC-007): CRUD de categorías desde su vista propia, bloqueo de la
 * eliminación cuando tiene libros asociados, y 404 en español ante registros
 * inexistentes.
 */
class CategoriaGestionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Texto exacto del `confirm()` de eliminación de una categoría (D-022).
     */
    private function textoDeConfirmacion(Categoria $categoria): string
    {
        return "confirm('¿Eliminar la categoría «{$categoria->nombre}»? Esta acción no se puede deshacer.')";
    }

    public function test_el_listado_muestra_las_categorias_con_su_cantidad_de_libros(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Ciencias exactas']);
        Libro::factory()->count(2)->create(['categoria_id' => $categoria->id]);
        Categoria::factory()->create(['nombre' => 'Literatura universal']);

        $respuesta = $this->get('/categorias');

        $respuesta->assertOk();
        $respuesta->assertSee('Ciencias exactas');
        $respuesta->assertSee('Literatura universal');

        $fila = $this->filaDeTablaQueContiene($respuesta->getContent(), 'Ciencias exactas');
        $this->assertStringContainsString('2', $fila, 'La fila debe mostrar la cantidad de libros asociados');
    }

    /**
     * Extrae el texto de la fila de tabla que contiene el texto indicado.
     */
    private function filaDeTablaQueContiene(string $html, string $texto): string
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        foreach ($xpath->query('//table//tr') as $fila) {
            if (str_contains($fila->textContent, $texto)) {
                return $fila->textContent;
            }
        }

        return '';
    }

    public function test_crear_una_categoria_valida_redirige_al_listado_con_mensaje_y_aparece_en_el_selector_de_libros(): void
    {
        $respuesta = $this->post('/categorias', ['nombre' => 'Filosofía']);

        $categoria = Categoria::query()->firstWhere('nombre', 'Filosofía');

        $this->assertNotNull($categoria);
        $respuesta->assertRedirect(route('categorias.index'));
        $respuesta->assertSessionHas('success', 'Categoría creada correctamente.');

        $this->get('/categorias')->assertOk()->assertSee('Filosofía');
        $this->get(route('libros.create'))->assertOk()->assertSee('Filosofía');
    }

    public function test_editar_una_categoria_renomabra_en_el_listado_y_en_el_selector(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Arte']);

        $respuesta = $this->put('/categorias/'.$categoria->id, ['nombre' => 'Arte y diseño']);

        $respuesta->assertRedirect(route('categorias.index'));
        $respuesta->assertSessionHas('success', 'Categoría actualizada correctamente.');

        $this->get('/categorias')->assertOk()->assertSee('Arte y diseño');
        $this->get(route('libros.create'))->assertOk()->assertSee('Arte y diseño');
    }

    public function test_eliminar_una_categoria_sin_libros_la_quita_del_listado_y_del_selector(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Temporal']);

        $respuesta = $this->delete('/categorias/'.$categoria->id);

        $respuesta->assertRedirect(route('categorias.index'));
        $respuesta->assertSessionHas('success', 'Categoría eliminada correctamente.');
        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);

        $this->get('/categorias')->assertOk()->assertDontSee('Temporal', false);
        $this->get(route('libros.create'))->assertOk()->assertDontSee('Temporal', false);
    }

    public function test_eliminar_una_categoria_con_libros_esta_bloqueada_con_mensaje_en_espanol(): void
    {
        $categoria = Categoria::factory()->create();
        Libro::factory()->create(['categoria_id' => $categoria->id]);

        $respuesta = $this->delete('/categorias/'.$categoria->id);

        $mensaje = 'La categoría tiene libros asociados y no puede eliminarse.';
        $respuesta->assertRedirect(route('categorias.index'));
        $respuesta->assertSessionHas('error', $mensaje);
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
    }

    public function test_el_listado_incluye_el_formulario_de_eliminar_con_confirmacion_y_csrf(): void
    {
        $categoria = Categoria::factory()->create();

        $respuesta = $this->get('/categorias');

        $respuesta->assertOk();
        $respuesta->assertSee('<form method="post" action="'.route('categorias.destroy', $categoria).'"', false);
        $respuesta->assertSee('name="_token"', false);
        $respuesta->assertSee('value="DELETE"', false);
        $respuesta->assertSee($this->textoDeConfirmacion($categoria), false);
    }

    public function test_un_get_a_la_ruta_de_borrado_de_categoria_no_elimina_y_responde_405(): void
    {
        $categoria = Categoria::factory()->create();

        $this->get('/categorias/'.$categoria->id)->assertStatus(405);

        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
    }

    public function test_una_categoria_inexistente_responde_404_en_espanol(): void
    {
        $this->get('/categorias/99999/editar')
            ->assertNotFound()
            ->assertSee('Categoría no encontrada');

        $this->put('/categorias/99999', ['nombre' => 'Inexistente'])
            ->assertNotFound()
            ->assertSee('Categoría no encontrada');

        $this->delete('/categorias/99999')
            ->assertNotFound()
            ->assertSee('Categoría no encontrada');
    }

    public function test_el_enlace_de_navegacion_de_categorias_aparece_en_el_layout(): void
    {
        $this->get('/catalogo')
            ->assertOk()
            ->assertSee(route('categorias.index'), false);
    }
}
