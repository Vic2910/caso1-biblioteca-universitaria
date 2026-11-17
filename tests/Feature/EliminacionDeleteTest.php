<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V-008 y SC-004: las eliminaciones solo existen por DELETE, se confirman en
 * español y respetan las reglas de negocio (FR-007, D-043).
 */
class EliminacionDeleteTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMACION = '¿Está seguro de que desea eliminar este registro?';

    public function test_el_get_sobre_las_urls_de_eliminacion_responde_405_sin_tocar_los_registros(): void
    {
        $libro = Libro::factory()->create();
        $categoria = Categoria::factory()->create();

        $urls = [
            route('libros.destroy', $libro, false),
            route('categorias.destroy', $categoria, false),
            route('libros.portadas.destroy', $libro, false),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertStatus(405);
            $this->get($url)->assertStatus(405);
        }

        $this->assertDatabaseHas('libros', ['id' => $libro->id]);
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
    }

    public function test_los_formularios_de_eliminacion_tienen_csrf_delete_y_confirmacion_en_espanol(): void
    {
        Libro::factory()->create();
        Categoria::factory()->create();

        foreach ([route('libros.index'), route('categorias.index')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('name="_token"', $html);
            $this->assertStringContainsString('name="_method"', $html);
            $this->assertStringContainsString('value="DELETE"', $html);
            $this->assertStringContainsString(self::CONFIRMACION, $html);
            $this->assertStringNotContainsString('onclick', $html);
        }
    }

    public function test_un_delete_valido_elimina_solo_el_registro_confirmado(): void
    {
        $eliminada = Categoria::factory()->create(['nombre' => 'Eliminada']);
        $conservada = Categoria::factory()->create(['nombre' => 'Conservada']);

        $this->delete(route('categorias.destroy', $eliminada))
            ->assertRedirect(route('categorias.index'));

        $this->assertDatabaseMissing('categorias', ['id' => $eliminada->id]);
        $this->assertDatabaseHas('categorias', ['id' => $conservada->id]);
    }

    public function test_una_eliminacion_bloqueada_conserva_el_registro_y_explica_el_motivo(): void
    {
        $categoria = Categoria::factory()->create();
        Libro::factory()->create(['categoria_id' => $categoria->id]);

        $this->delete(route('categorias.destroy', $categoria))
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('error', 'La categoría tiene libros asociados y no puede eliminarse.');

        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
    }
}
