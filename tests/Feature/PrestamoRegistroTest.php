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
 * Cubre los escenarios V-001, V-005 y V-006 de specs/004-gestion-prestamos/
 * quickstart.md (FR-001, FR-003, FR-005, SC-001, SC-002, SC-004): registro
 * válido con descuento exacto de disponibilidad y rechazos en español junto
 * al campo conservando lo ingresado con `old()`.
 */
class PrestamoRegistroTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Libro de prueba con un número determinista de ejemplares.
     */
    private function libroConEjemplares(int $ejemplares): Libro
    {
        return Libro::factory()->create([
            'categoria_id' => Categoria::factory()->create()->id,
            'ejemplares_totales' => $ejemplares,
            'ejemplares_disponibles' => $ejemplares,
        ]);
    }

    /**
     * Datos válidos de registro sobre un libro existente.
     *
     * @return array<string, mixed>
     */
    private function datosValidos(?Libro $libro = null): array
    {
        return [
            'libro_id' => ($libro ?? $this->libroConEjemplares(3))->id,
            'nombre_estudiante' => 'María Gómez Soto',
            'carnet_estudiante' => '202500111',
            'fecha_prestamo' => now()->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->addDays(14)->format('Y-m-d'),
        ];
    }

    /**
     * Comprueba que el mensaje de error aparece en el mismo bloque que el
     * campo, es decir, inmediatamente debajo del input correspondiente
     * (SC-004).
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

    public function test_el_registro_valido_crea_el_prestamo_activo_y_descuenta_un_ejemplar(): void
    {
        $libro = $this->libroConEjemplares(3);

        $respuesta = $this->post('/prestamos', $this->datosValidos($libro));

        $prestamo = Prestamo::query()->firstWhere('carnet_estudiante', '202500111');

        $this->assertNotNull($prestamo, 'El préstamo debe haberse creado');
        $respuesta->assertRedirect(route('prestamos.index'));
        $respuesta->assertSessionHas('success', 'Préstamo registrado correctamente.');

        $this->assertSame(Prestamo::ESTADO_ACTIVO, $prestamo->estado);
        $this->assertSame($libro->id, $prestamo->libro_id);
        $this->assertSame('María Gómez Soto', $prestamo->nombre_estudiante);
        $this->assertNull($prestamo->fecha_devolucion_real);

        $this->assertSame(2, $libro->fresh()->ejemplares_disponibles, 'La disponibilidad debe disminuir exactamente en uno');
    }

    public function test_la_devolucion_prevista_igual_o_anterior_al_prestamo_se_rechaza_conservando_lo_ingresado(): void
    {
        $mensaje = 'La devolución prevista debe ser posterior a la fecha de préstamo.';
        $libro = $this->libroConEjemplares(2);

        foreach (['mismo' => 0, 'anterior' => -1] as $caso => $desplazamiento) {
            $respuesta = $this
                ->from(route('prestamos.create'))
                ->post('/prestamos', array_merge($this->datosValidos($libro), [
                    'fecha_devolucion_prevista' => now()->addDays($desplazamiento)->format('Y-m-d'),
                ]));

            $respuesta->assertRedirect(route('prestamos.create'));
            $respuesta->assertSessionHasErrors(['fecha_devolucion_prevista' => $mensaje]);
            $this->assertDatabaseCount('prestamos', 0);
            $this->assertSame(2, $libro->fresh()->ejemplares_disponibles, "Caso {$caso}: la disponibilidad no cambia");
        }

        $html = $this
            ->followingRedirects()
            ->from(route('prestamos.create'))
            ->post('/prestamos', array_merge($this->datosValidos($libro), [
                'fecha_devolucion_prevista' => now()->format('Y-m-d'),
            ]))
            ->getContent();

        $this->assertErrorJuntoAlCampo($html, 'fecha_devolucion_prevista', $mensaje);
        $this->assertStringContainsString('María Gómez Soto', $html, 'Los valores ingresados se conservan con old()');
    }

    public function test_un_libro_inexistente_se_rechaza_en_espanol_junto_al_campo(): void
    {
        $mensaje = 'El libro seleccionado no existe.';

        $respuesta = $this
            ->from(route('prestamos.create'))
            ->post('/prestamos', array_merge($this->datosValidos(), ['libro_id' => 999999]));

        $respuesta->assertRedirect(route('prestamos.create'));
        $respuesta->assertSessionHasErrors(['libro_id' => $mensaje]);
        $this->assertDatabaseCount('prestamos', 0);

        $html = $this
            ->followingRedirects()
            ->from(route('prestamos.create'))
            ->post('/prestamos', array_merge($this->datosValidos(), ['libro_id' => 999999]))
            ->getContent();

        $this->assertErrorJuntoAlCampo($html, 'libro_id', $mensaje);
    }

    public function test_el_formulario_vacio_rechaza_cada_campo_obligatorio_en_espanol(): void
    {
        $respuesta = $this->from(route('prestamos.create'))->post('/prestamos', []);

        $respuesta->assertRedirect(route('prestamos.create'));
        $respuesta->assertSessionHasErrors([
            'libro_id' => 'El campo libro es obligatorio.',
            'nombre_estudiante' => 'El campo nombre del estudiante es obligatorio.',
            'carnet_estudiante' => 'El campo carné es obligatorio.',
            'fecha_prestamo' => 'La fecha de préstamo es obligatoria.',
            'fecha_devolucion_prevista' => 'La devolución prevista es obligatoria.',
        ]);
        $this->assertDatabaseCount('prestamos', 0);
    }

    public function test_el_formulario_muestra_los_libros_incluidos_los_agotados_y_la_fecha_de_prestamo_de_hoy(): void
    {
        $disponible = $this->libroConEjemplares(2);
        $agotado = $this->libroConEjemplares(0);

        $respuesta = $this->get(route('prestamos.create'));

        $respuesta->assertOk();
        $respuesta->assertSee($disponible->titulo);
        $respuesta->assertSee('disponibles: 2');
        $respuesta->assertSee($agotado->titulo);
        $respuesta->assertSee('disponibles: 0');
        $respuesta->assertSee('Seleccione un libro');
        $respuesta->assertSee(now()->format('Y-m-d'), false);
    }
}
