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
 * Cubre los escenarios V-002, V-008, V-009 y V-012 de specs/004-gestion-
 * prestamos/quickstart.md (FR-002, FR-004, FR-006, FR-007, SC-001, SC-002,
 * SC-004): devolución de préstamo `activo` y `vencido` con disponibilidad +1
 * exactamente una vez, rechazo de la devolución repetida (M2), errores en
 * español junto al campo con `old()`, 404 controlado y ausencia de rutas de
 * modificación.
 */
class PrestamoDevolucionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Libro con la disponibilidad ya descontada como si estuviera prestado.
     */
    private function libroPrestado(): Libro
    {
        $libro = Libro::factory()->create([
            'categoria_id' => Categoria::factory()->create()->id,
            'ejemplares_totales' => 3,
            'ejemplares_disponibles' => 3,
        ]);

        Prestamo::factory()->activo()->create(['libro_id' => $libro->id]);

        Libro::query()->whereKey($libro->id)->decrement('ejemplares_disponibles');

        return $libro;
    }

    /**
     * Comprueba que el mensaje de error aparece en el mismo bloque que el
     * campo (SC-004), inmediatamente debajo del input correspondiente.
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

    public function test_devolver_un_activo_lo_marca_devuelto_y_suma_la_disponibilidad_exactamente_una_vez(): void
    {
        $libro = $this->libroPrestado();
        $prestamo = Prestamo::query()->firstWhere('libro_id', $libro->id);

        $respuesta = $this->post(route('prestamos.devolver', $prestamo), [
            'fecha_devolucion_real' => now()->format('Y-m-d'),
        ]);

        $respuesta->assertRedirect(route('prestamos.index'));
        $respuesta->assertSessionHas('success', 'Devolución registrada correctamente.');

        $prestamo->refresh();
        $this->assertSame(Prestamo::ESTADO_DEVUELTO, $prestamo->estado);
        $this->assertNotNull($prestamo->fecha_devolucion_real, 'La fecha real debe quedar fijada');
        $this->assertSame(3, $libro->fresh()->ejemplares_disponibles, 'La disponibilidad sube exactamente una vez');

        $formulario = $this->get(route('prestamos.form-devolucion', $prestamo));
        $formulario->assertOk();
        $formulario->assertSee('Este préstamo ya fue devuelto.');
        $this->assertStringNotContainsString(
            '<button type="submit"',
            $formulario->getContent(),
            'Un préstamo devuelto no ofrece botón de envío'
        );
    }

    public function test_devolver_un_vencido_esta_permitido(): void
    {
        $libro = Libro::factory()->create([
            'categoria_id' => Categoria::factory()->create()->id,
            'ejemplares_totales' => 2,
            'ejemplares_disponibles' => 2,
        ]);

        $prestamo = Prestamo::factory()->vencido()->create(['libro_id' => $libro->id]);

        Libro::query()->whereKey($libro->id)->decrement('ejemplares_disponibles');

        $this->assertSame(1, $libro->fresh()->ejemplares_disponibles, 'El vencido ocupa su ejemplar (invariante 004)');

        $respuesta = $this->post(route('prestamos.devolver', $prestamo), [
            'fecha_devolucion_real' => now()->format('Y-m-d'),
        ]);

        $respuesta->assertSessionHasNoErrors();
        $respuesta->assertSessionHas('success', 'Devolución registrada correctamente.');

        $prestamo->refresh();
        $this->assertSame(Prestamo::ESTADO_DEVUELTO, $prestamo->estado);
        $this->assertSame(2, $libro->fresh()->ejemplares_disponibles, 'La disponibilidad sube exactamente una vez');
    }

    public function test_la_devolucion_repetida_se_rechaza_sin_volver_a_sumar(): void
    {
        $libro = $this->libroPrestado();
        $prestamo = Prestamo::query()->firstWhere('libro_id', $libro->id);

        $this->post(route('prestamos.devolver', $prestamo), [
            'fecha_devolucion_real' => now()->format('Y-m-d'),
        ])->assertSessionHasNoErrors();

        $segunda = $this
            ->from(route('prestamos.form-devolucion', $prestamo))
            ->post(route('prestamos.devolver', $prestamo), [
                'fecha_devolucion_real' => now()->format('Y-m-d'),
            ]);

        $segunda->assertRedirect(route('prestamos.form-devolucion', $prestamo));
        $segunda->assertSessionHasErrors(['devolucion' => 'Este préstamo ya fue devuelto.']);
        $this->assertSame(Prestamo::ESTADO_DEVUELTO, $prestamo->fresh()->estado);
        $this->assertSame(3, $libro->fresh()->ejemplares_disponibles, 'La disponibilidad no se suma dos veces');

        $html = $this
            ->followingRedirects()
            ->from(route('prestamos.form-devolucion', $prestamo))
            ->post(route('prestamos.devolver', $prestamo), [
                'fecha_devolucion_real' => now()->format('Y-m-d'),
            ])
            ->getContent();

        $this->assertStringContainsString('Este préstamo ya fue devuelto.', $html);
    }

    public function test_fecha_real_vacia_o_anterior_al_prestamo_se_rechaza_junto_al_campo_conservando_lo_ingresado(): void
    {
        $libro = $this->libroPrestado();
        $prestamo = Prestamo::query()->firstWhere('libro_id', $libro->id);

        $vacia = $this
            ->from(route('prestamos.form-devolucion', $prestamo))
            ->post(route('prestamos.devolver', $prestamo), ['fecha_devolucion_real' => '']);

        $vacia->assertRedirect(route('prestamos.form-devolucion', $prestamo));
        $vacia->assertSessionHasErrors(['fecha_devolucion_real' => 'La fecha de devolución real es obligatoria.']);

        $anterior = $this
            ->from(route('prestamos.form-devolucion', $prestamo))
            ->post(route('prestamos.devolver', $prestamo), [
                'fecha_devolucion_real' => $prestamo->fecha_prestamo->copy()->subDay()->format('Y-m-d'),
            ]);

        $anterior->assertRedirect(route('prestamos.form-devolucion', $prestamo));
        $anterior->assertSessionHasErrors(['fecha_devolucion_real' => 'La fecha de devolución real no puede ser anterior a la fecha de préstamo.']);

        $this->assertSame(Prestamo::ESTADO_ACTIVO, $prestamo->fresh()->estado);
        $this->assertSame(2, $libro->fresh()->ejemplares_disponibles, 'Ningún rechazo modifica la disponibilidad');

        $html = $this
            ->followingRedirects()
            ->from(route('prestamos.form-devolucion', $prestamo))
            ->post(route('prestamos.devolver', $prestamo), ['fecha_devolucion_real' => ''])
            ->getContent();

        $this->assertErrorJuntoAlCampo($html, 'fecha_devolucion_real', 'La fecha de devolución real es obligatoria.');
    }

    public function test_un_prestamo_inexistente_responde_404_en_espanol(): void
    {
        $this->get(route('prestamos.form-devolucion', 999999))
            ->assertNotFound()
            ->assertSee('Préstamo no encontrado')
            ->assertSee('Volver a préstamos');

        $this->post(route('prestamos.devolver', 999999), [
            'fecha_devolucion_real' => now()->format('Y-m-d'),
        ])->assertNotFound();
    }

    public function test_no_existen_rutas_put_patch_ni_delete_de_prestamos(): void
    {
        $libro = $this->libroPrestado();
        $prestamo = Prestamo::query()->firstWhere('libro_id', $libro->id);

        foreach (['put', 'patch', 'delete'] as $metodo) {
            $this->{$metodo}('/prestamos/'.$prestamo->id)->assertNotFound();
            $this->{$metodo}(route('prestamos.devolver', $prestamo))->assertStatus(405);
        }

        $this->assertSame(Prestamo::ESTADO_ACTIVO, $prestamo->fresh()->estado, 'El préstamo no cambia por verbos inexistentes');
    }
}
