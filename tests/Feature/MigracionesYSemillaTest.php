<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cubre los escenarios V-002 (esquema versionado), V-003 (datos de prueba)
 * y V-004 (relaciones) de specs/001-entorno-datos/quickstart.md.
 *
 * Cada prueba refresca el esquema por sí misma (sin RefreshDatabase) porque
 * migrate:rollback ejecuta DDL, que provoca commits implícitos en MySQL y
 * rompería las transacciones de prueba.
 */
class MigracionesYSemillaTest extends TestCase
{
    public function test_las_migraciones_estan_completamente_ejecutadas(): void
    {
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();

        Artisan::call('migrate:status', ['--no-interaction' => true]);
        $salida = Artisan::output();

        $this->assertStringContainsString('Ran', $salida);
        $this->assertStringNotContainsString('Pending', $salida);
    }

    public function test_el_esquema_es_reversible_y_reaplicable_sin_errores(): void
    {
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--no-interaction' => true])->assertSuccessful();
        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();

        Artisan::call('migrate:status', ['--no-interaction' => true]);

        $this->assertStringNotContainsString('Pending', Artisan::output());
    }

    public function test_las_claves_foraneas_bloquean_el_borrado_con_restrict(): void
    {
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();

        $reglas = DB::select(
            "SELECT TABLE_NAME, CONSTRAINT_NAME, DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME IN ('libros_categoria_id_foreign', 'prestamos_libro_id_foreign')"
        );

        $borrados = [];
        foreach ($reglas as $regla) {
            $borrados[$regla->CONSTRAINT_NAME] = $regla->DELETE_RULE;
        }

        $this->assertSame(
            'RESTRICT',
            $borrados['libros_categoria_id_foreign'] ?? null,
            'La clave foránea libros.categoria_id debe tener ON DELETE RESTRICT'
        );
        $this->assertSame(
            'RESTRICT',
            $borrados['prestamos_libro_id_foreign'] ?? null,
            'La clave foránea prestamos.libro_id debe tener ON DELETE RESTRICT'
        );
    }

    public function test_los_indices_unicos_de_isbn_y_nombre_de_categoria_existen(): void
    {
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();

        $indices = DB::select(
            "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND INDEX_NAME IN ('libros_isbn_unique', 'categorias_nombre_unique')
             GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE"
        );

        $unicos = [];
        foreach ($indices as $indice) {
            $unicos[$indice->INDEX_NAME] = (int) $indice->NON_UNIQUE === 0;
        }

        $this->assertTrue(
            $unicos['libros_isbn_unique'] ?? false,
            'El índice libros_isbn_unique debe existir y ser único'
        );
        $this->assertTrue(
            $unicos['categorias_nombre_unique'] ?? false,
            'El índice categorias_nombre_unique debe existir y ser único'
        );
    }

    public function test_la_semilla_carga_el_catalogo_minimo_exigido(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true])->assertSuccessful();

        $this->assertGreaterThanOrEqual(15, Libro::count(), 'Debe haber al menos 15 libros');
        $this->assertGreaterThanOrEqual(3, Categoria::count(), 'Debe haber al menos 3 categorías');
        $this->assertSame(3, Prestamo::count(), 'Debe haber exactamente 3 préstamos');

        $estados = Prestamo::query()->pluck('estado')->sort()->values()->all();
        $this->assertSame(['activo', 'devuelto', 'vencido'], $estados, 'Los tres estados deben estar representados');

        $this->assertSame(0, Libro::whereNotNull('portada')->count(), 'Todos los libros de prueba deben tener portada NULL');
    }

    public function test_todos_los_libros_cumplen_el_invariante_de_disponibilidad(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true])->assertSuccessful();

        $libros = Libro::all();

        $this->assertGreaterThan(0, $libros->count());

        foreach ($libros as $libro) {
            $prestamosActivos = Prestamo::query()
                ->where('libro_id', $libro->id)
                ->where('estado', 'activo')
                ->count();

            $this->assertSame(
                $libro->ejemplares_totales - $prestamosActivos,
                $libro->ejemplares_disponibles,
                "El libro {$libro->id} no cumple: disponibles = totales − préstamos activos"
            );
        }
    }

    public function test_las_relaciones_apuntan_a_registros_existentes(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true])->assertSuccessful();

        $idsCategorias = Categoria::query()->pluck('id');
        $idsLibros = Libro::query()->pluck('id');

        $this->assertSame(
            0,
            Libro::query()->whereNotIn('categoria_id', $idsCategorias)->count(),
            'El 100 % de los libros debe tener una categoría válida'
        );
        $this->assertSame(
            0,
            Prestamo::query()->whereNotIn('libro_id', $idsLibros)->count(),
            'El 100 % de los préstamos debe apuntar a un libro existente'
        );

        $this->assertInstanceOf(Categoria::class, Libro::query()->first()->categoria);
        $this->assertInstanceOf(Libro::class, Prestamo::query()->first()->libro);
    }
}
