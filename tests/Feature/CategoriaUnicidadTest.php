<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el escenario V-011 de specs/003-gestion-libros/quickstart.md
 * (FR-008, SC-007): el nombre de categoría es único ignorando mayúsculas,
 * tildes y espacios sobrantes, con el error en español junto al campo,
 * tanto al crear como al editar.
 */
class CategoriaUnicidadTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE_DUPLICADO = 'Ese nombre de categoría ya existe.';

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

    /**
     * Variantes que deben chocar con «Matemáticas» (tildes, mayúsculas, espacios).
     *
     * @return list<array{string}>
     */
    public static function variantesDeMatematicas(): array
    {
        return [
            ['matematicas '],
            ['MATEMATICAS'],
            ['Matematicas'],
            [' matemáticas'],
        ];
    }

    public function test_crear_cualquiera_de_las_variantes_se_rechaza_junto_al_campo_sin_crear_la_categoria(): void
    {
        Categoria::factory()->create(['nombre' => 'Matemáticas']);

        foreach (self::variantesDeMatematicas() as [$variante]) {
            $respuesta = $this
                ->from(route('categorias.create'))
                ->post('/categorias', ['nombre' => $variante]);

            $respuesta->assertRedirect(route('categorias.create'));
            $respuesta->assertSessionHasErrors(['nombre' => self::MENSAJE_DUPLICADO]);
            $this->assertDatabaseCount('categorias', 1);
        }
    }

    public function test_editar_otra_categoria_hacia_una_variante_se_rechaza_junto_al_campo(): void
    {
        Categoria::factory()->create(['nombre' => 'Matemáticas']);
        $otra = Categoria::factory()->create(['nombre' => 'Historia']);

        foreach (self::variantesDeMatematicas() as [$variante]) {
            $respuesta = $this
                ->from(route('categorias.edit', $otra))
                ->put('/categorias/'.$otra->id, ['nombre' => $variante]);

            $respuesta->assertRedirect(route('categorias.edit', $otra));
            $respuesta->assertSessionHasErrors(['nombre' => self::MENSAJE_DUPLICADO]);
            $this->assertSame('Historia', $otra->fresh()->nombre);
        }
    }

    public function test_editar_una_categoria_conservando_su_propio_nombre_es_valido(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Matemáticas']);

        $respuesta = $this
            ->from(route('categorias.edit', $categoria))
            ->put('/categorias/'.$categoria->id, ['nombre' => 'Matemáticas']);

        $respuesta->assertSessionHasNoErrors();
        $this->assertSame('Matemáticas', $categoria->fresh()->nombre);
    }

    public function test_el_nombre_almacenado_conserva_la_forma_original_recortada(): void
    {
        $this->post('/categorias', ['nombre' => '  Filosofía  '])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categorias', ['nombre' => 'Filosofía']);
    }

    public function test_un_nombre_vacio_tras_el_requiere_espanol_junto_al_campo(): void
    {
        $respuesta = $this
            ->from(route('categorias.create'))
            ->post('/categorias', ['nombre' => '   ']);

        $mensaje = 'El campo Nombre es obligatorio.';
        $respuesta->assertRedirect(route('categorias.create'));
        $respuesta->assertSessionHasErrors(['nombre' => $mensaje]);
        $this->assertDatabaseCount('categorias', 0);

        $html = $this->followingRedirects()
            ->from(route('categorias.create'))
            ->post('/categorias', ['nombre' => '   '])
            ->assertOk()
            ->getContent();
        $this->assertErrorJuntoAlCampo($html, 'nombre', $mensaje);
    }
}
