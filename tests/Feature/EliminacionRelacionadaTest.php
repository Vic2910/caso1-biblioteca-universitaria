<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\RegistrosRelacionadosException;
use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use App\Services\EliminacionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cubre el escenario V-006 (conducta de eliminación, FR-006/SC-005) y el
 * contrato specs/001-entorno-datos/contracts/user-messages.md §2:
 * bloqueo en app + RESTRICT en BD cuando existen registros relacionados,
 * y eliminación correcta cuando no los hay.
 */
class EliminacionRelacionadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_eliminar_categoria_con_libros_esta_bloqueada_en_app(): void
    {
        $categoria = Categoria::factory()->create();
        Libro::factory()->create(['categoria_id' => $categoria->id]);

        try {
            app(EliminacionService::class)->eliminarCategoria($categoria);
            $this->fail('Eliminar una categoría con libros debe estar bloqueado');
        } catch (RegistrosRelacionadosException $e) {
            $this->assertStringContainsString('libros asociados', $e->getMessage());
            $this->assertStringContainsString('no puede eliminarse', $e->getMessage());
        }

        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
    }

    public function test_eliminar_categoria_con_libros_falla_tambien_en_base_de_datos(): void
    {
        $categoria = Categoria::factory()->create();
        Libro::factory()->create(['categoria_id' => $categoria->id]);

        $this->expectException(QueryException::class);

        // Eliminación directa (sin pasar por la app) debe fallar por RESTRICT (D-004)
        DB::table('categorias')->where('id', $categoria->id)->delete();
    }

    public function test_eliminar_libro_con_prestamos_esta_bloqueado_en_app(): void
    {
        $libro = Libro::factory()->create();
        Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);

        try {
            app(EliminacionService::class)->eliminarLibro($libro);
            $this->fail('Eliminar un libro con préstamos debe estar bloqueado');
        } catch (RegistrosRelacionadosException $e) {
            $this->assertStringContainsString('préstamos asociados', $e->getMessage());
            $this->assertStringContainsString('no puede eliminarse', $e->getMessage());
        }

        $this->assertDatabaseHas('libros', ['id' => $libro->id]);
    }

    public function test_eliminar_libro_con_prestamos_falla_tambien_en_base_de_datos(): void
    {
        $libro = Libro::factory()->create();
        Prestamo::factory()->devuelto()->create(['libro_id' => $libro->id]);

        $this->expectException(QueryException::class);

        DB::table('libros')->where('id', $libro->id)->delete();
    }

    public function test_eliminar_categoria_sin_libros_es_correcto(): void
    {
        $categoria = Categoria::factory()->create();

        app(EliminacionService::class)->eliminarCategoria($categoria);

        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    }

    public function test_eliminar_libro_sin_prestamos_es_correcto(): void
    {
        $libro = Libro::factory()->create();

        app(EliminacionService::class)->eliminarLibro($libro);

        $this->assertDatabaseMissing('libros', ['id' => $libro->id]);
    }
}
