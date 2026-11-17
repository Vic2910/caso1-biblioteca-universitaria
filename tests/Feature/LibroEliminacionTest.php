<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los escenarios V-006 y V-007 de specs/003-gestion-libros/quickstart.md
 * (FR-005, FR-009, SC-003): eliminación con confirmación previa mediante
 * DELETE, bloqueo en español cuando el libro tiene préstamos registrados,
 * y que GET nunca elimina.
 */
class LibroEliminacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Texto exacto del `confirm()` de eliminación de un libro (D-022).
     */
    private function textoDeConfirmacion(Libro $libro): string
    {
        return "confirm('¿Eliminar el libro «{$libro->titulo}»? Esta acción no se puede deshacer.')";
    }

    public function test_eliminar_un_libro_sin_prestamos_redirige_al_catalogo_con_mensaje_de_exito(): void
    {
        $libro = Libro::factory()->create();

        $respuesta = $this->delete('/libros/'.$libro->id);

        $respuesta->assertRedirect(route('catalogo.index'));
        $respuesta->assertSessionHas('success', 'Libro eliminado correctamente.');
        $this->assertDatabaseMissing('libros', ['id' => $libro->id]);
    }

    public function test_eliminar_un_libro_con_prestamos_esta_bloqueado_con_mensaje_en_espanol(): void
    {
        $libro = Libro::factory()->create();
        Prestamo::factory()->devuelto()->create(['libro_id' => $libro->id]);

        $respuesta = $this->delete('/libros/'.$libro->id);

        $mensaje = 'El libro tiene préstamos registrados y no puede eliminarse.';
        $respuesta->assertRedirect(route('catalogo.index'));
        $respuesta->assertSessionHas('error', $mensaje);
        $this->assertDatabaseHas('libros', ['id' => $libro->id]);
    }

    public function test_la_ficha_muestra_el_formulario_de_eliminar_con_confirmacion_y_csrf(): void
    {
        $libro = Libro::factory()->create();

        $respuesta = $this->get(route('catalogo.show', $libro));

        $respuesta->assertOk();
        $respuesta->assertSee('<form method="post" action="'.route('libros.destroy', $libro).'"', false);
        $respuesta->assertSee('name="_token"', false);
        $respuesta->assertSee('name="_method"', false);
        $respuesta->assertSee('value="DELETE"', false);
        $respuesta->assertSee($this->textoDeConfirmacion($libro), false);
    }

    public function test_el_aviso_de_bloqueo_se_muestra_en_la_ficha_antes_de_confirmar(): void
    {
        $libro = Libro::factory()->create();
        Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);

        $this->get(route('catalogo.show', $libro))
            ->assertOk()
            ->assertSee('Este libro tiene préstamos registrados y no puede eliminarse.');
    }

    public function test_un_get_a_la_ruta_de_borrado_no_elimina_y_responde_405(): void
    {
        $libro = Libro::factory()->create();

        $respuesta = $this->get('/libros/'.$libro->id);

        $respuesta->assertStatus(405);
        $this->assertDatabaseHas('libros', ['id' => $libro->id]);
    }

    public function test_eliminar_un_libro_inexistente_responde_404_en_espanol(): void
    {
        $this->delete('/libros/99999')
            ->assertNotFound()
            ->assertSee('Libro no encontrado');
    }

    public function test_tras_eliminar_la_ficha_responde_404(): void
    {
        $libro = Libro::factory()->create();
        $this->delete('/libros/'.$libro->id);

        $this->get(route('catalogo.show', $libro))->assertNotFound();
    }
}
