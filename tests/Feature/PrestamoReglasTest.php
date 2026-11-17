<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los escenarios V-003, V-004 y V-007 de specs/004-gestion-prestamos/
 * quickstart.md (FR-004, FR-005, SC-002): rechazo sin disponibilidad (M1),
 * límite de tres préstamos no devueltos contando `activo` + `vencido` con
 * carné normalizado por `trim()`, y la carrera determinista por el último
 * ejemplar sin disponibilidad jamás negativa.
 */
class PrestamoReglasTest extends TestCase
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
    private function datosValidos(Libro $libro, string $carnet = '202500222'): array
    {
        return [
            'libro_id' => $libro->id,
            'nombre_estudiante' => 'Pedro Sánchez Ruiz',
            'carnet_estudiante' => $carnet,
            'fecha_prestamo' => now()->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->addDays(14)->format('Y-m-d'),
        ];
    }

    public function test_un_libro_sin_disponibilidad_se_rechaza_con_mensaje_junto_al_campo_libro(): void
    {
        $agotado = $this->libroConEjemplares(0);
        $mensaje = 'No hay ejemplares disponibles de este libro.';

        $respuesta = $this
            ->from(route('prestamos.create'))
            ->post('/prestamos', $this->datosValidos($agotado));

        $respuesta->assertRedirect(route('prestamos.create'));
        $respuesta->assertSessionHasErrors(['libro_id' => $mensaje]);
        $this->assertDatabaseCount('prestamos', 0);
        $this->assertSame(0, $agotado->fresh()->ejemplares_disponibles, 'La disponibilidad no cambia en el rechazo');

        $html = $this
            ->followingRedirects()
            ->from(route('prestamos.create'))
            ->post('/prestamos', $this->datosValidos($agotado))
            ->getContent();

        $this->assertStringContainsString($mensaje, $html);
        $this->assertErrorJuntoAlCampo($html, 'libro_id', $mensaje);
    }

    public function test_el_limite_de_tres_no_devueltos_cuenta_activos_y_vencidos(): void
    {
        $libro = $this->libroConEjemplares(10);
        $carnet = '202500333';

        Prestamo::factory()->activo()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);
        Prestamo::factory()->vencido()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);
        Prestamo::factory()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);

        $mensaje = 'El estudiante ya tiene el máximo de tres préstamos sin devolver.';

        $respuesta = $this
            ->from(route('prestamos.create'))
            ->post('/prestamos', $this->datosValidos($libro, $carnet));

        $respuesta->assertRedirect(route('prestamos.create'));
        $respuesta->assertSessionHasErrors(['carnet_estudiante' => $mensaje]);
        $this->assertDatabaseCount('prestamos', 3);
        $this->assertSame(10, $libro->fresh()->ejemplares_disponibles, 'La disponibilidad no cambia en el rechazo');
    }

    public function test_un_carnet_con_espacios_sobrantes_cuenta_como_el_mismo_estudiante(): void
    {
        $libro = $this->libroConEjemplares(10);
        $carnet = '12345';

        Prestamo::factory()->activo()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);
        Prestamo::factory()->vencido()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);
        Prestamo::factory()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);

        $respuesta = $this->post('/prestamos', $this->datosValidos($libro, ' 12345 '));

        $respuesta->assertSessionHasErrors([
            'carnet_estudiante' => 'El estudiante ya tiene el máximo de tres préstamos sin devolver.',
        ]);
        $this->assertDatabaseCount('prestamos', 3);
    }

    public function test_al_devolverse_un_prestamo_la_plaza_liberada_permite_un_nuevo_registro(): void
    {
        $libro = $this->libroConEjemplares(10);
        $carnet = '202500444';

        $primer = Prestamo::factory()->activo()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);
        Prestamo::factory()->vencido()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);
        Prestamo::factory()->create(['libro_id' => $libro->id, 'carnet_estudiante' => $carnet]);

        $denegado = $this->post('/prestamos', $this->datosValidos($libro, $carnet));
        $denegado->assertSessionHasErrors(['carnet_estudiante' => 'El estudiante ya tiene el máximo de tres préstamos sin devolver.']);

        // La devolución (US2) pasa el préstamo a `devuelto` y deja de contar.
        $primer->update([
            'estado' => Prestamo::ESTADO_DEVUELTO,
            'fecha_devolucion_real' => now()->format('Y-m-d'),
        ]);

        $respuesta = $this->post('/prestamos', $this->datosValidos($libro, $carnet));

        $respuesta->assertSessionHasNoErrors();
        $this->assertDatabaseCount('prestamos', 4);
    }

    public function test_la_carrera_por_el_ultimo_ejemplar_se_rechaza_sin_disponibilidad_negativa(): void
    {
        $libro = $this->libroConEjemplares(1);

        $primera = $this->post('/prestamos', $this->datosValidos($libro, '202500555'));
        $primera->assertSessionHasNoErrors();
        $this->assertSame(0, $libro->fresh()->ejemplares_disponibles, 'El primer registro consume el último ejemplar');

        $segunda = $this
            ->from(route('prestamos.create'))
            ->post('/prestamos', $this->datosValidos($libro, '202500666'));

        $segunda->assertRedirect(route('prestamos.create'));
        $segunda->assertSessionHasErrors(['libro_id' => 'No hay ejemplares disponibles de este libro.']);
        $this->assertDatabaseCount('prestamos', 1);
        $this->assertSame(0, $libro->fresh()->ejemplares_disponibles, 'La disponibilidad queda en cero, nunca negativa');
    }

    /**
     * Comprueba que el mensaje de error aparece en el mismo bloque que el
     * campo (SC-004), inmediatamente debajo del input correspondiente.
     */
    private function assertErrorJuntoAlCampo(string $html, string $campo, string $mensaje): void
    {
        $documento = new \DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($documento);

        $nodos = $xpath->query(sprintf('//*[@name="%s"]', $campo));
        $this->assertNotEmpty($nodos, "El formulario debe contener el campo {$campo}");

        $this->assertStringContainsString(
            $mensaje,
            $nodos->item(0)->parentNode->textContent,
            "El mensaje debe mostrarse junto al campo {$campo}"
        );
    }
}
