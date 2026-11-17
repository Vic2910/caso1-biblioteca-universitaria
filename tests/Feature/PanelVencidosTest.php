<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\Prestamo;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lista de préstamos vencidos del panel (specs/007-panel-biblioteca, FR-002/
 * FR-005, D-020/D-022): regla estricta de fechas, exclusión de devueltos,
 * campos visibles, orden por retraso y una fila por préstamo pendiente.
 */
class PanelVencidosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Filas de la sección de vencidos, con sus seis celdas de texto.
     *
     * @return list<list<string>>
     */
    private function vencidosDe(string $html): array
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $seccion = $xpath->query('//section[contains(@class, "panel-seccion")][normalize-space(./h3)="Préstamos vencidos"]')->item(0);

        $filas = [];

        if ($seccion !== null) {
            foreach ($xpath->query('.//tbody/tr', $seccion) as $fila) {
                $celdas = [];
                foreach ($xpath->query('./td', $fila) as $celda) {
                    $celdas[] = trim($celda->textContent);
                }
                $filas[] = $celdas;
            }
        }

        return $filas;
    }

    public function test_solo_vencen_prestamos_pendientes_con_prevista_estrictamente_anterior_a_hoy(): void
    {
        $this->travelTo('2026-11-17 12:00:00');

        foreach ([
            'Prevista hoy' => '2026-11-17',
            'Prevista mañana' => '2026-11-18',
            'Prevista ayer' => '2026-11-16',
            'Prevista hace tres días' => '2026-11-14',
        ] as $titulo => $prevista) {
            $libro = Libro::factory()->create(['titulo' => $titulo]);
            Prestamo::factory()->for($libro)->create([
                'fecha_devolucion_prevista' => $prevista,
                'fecha_devolucion_real' => null,
                'estado' => Prestamo::ESTADO_ACTIVO,
            ]);
        }

        $filas = $this->vencidosDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            [['Prevista hace tres días', '3'], ['Prevista ayer', '1']],
            array_map(fn (array $fila): array => [$fila[0], $fila[5]], $filas),
            'La prevista igual a hoy no vence y la de mañana tampoco (regla estricta, FR-005)'
        );
    }

    public function test_un_prestamo_devuelto_con_retraso_no_aparece_en_vencidos(): void
    {
        $this->travelTo('2026-11-17 12:00:00');

        Prestamo::factory()->devuelto()->create([
            'fecha_devolucion_prevista' => '2026-11-01',
            'fecha_devolucion_real' => '2026-11-10',
        ]);

        $respuesta = $this->get('/panel')->assertOk();

        $this->assertSame([], $this->vencidosDe($respuesta->getContent()));
        $respuesta->assertSee('No hay datos disponibles');
    }

    public function test_la_fila_de_vencidos_muestra_libro_estudiante_carnet_y_fechas(): void
    {
        $this->travelTo('2026-11-17 12:00:00');

        $libro = Libro::factory()->create(['titulo' => 'Zoología urbana']);
        Prestamo::factory()->for($libro)->create([
            'nombre_estudiante' => 'Ana Pérez',
            'carnet_estudiante' => '20261117',
            'fecha_prestamo' => '2026-10-01',
            'fecha_devolucion_prevista' => '2026-11-14',
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_VENCIDO,
        ]);

        $filas = $this->vencidosDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            [['Zoología urbana', 'Ana Pérez', '20261117', '2026-10-01', '2026-11-14', '3']],
            $filas,
            'Todos los campos exigidos por FR-005 deben estar presentes y en orden'
        );
    }

    public function test_los_empates_en_dias_de_retraso_se_resuelven_por_titulo_del_libro(): void
    {
        $this->travelTo('2026-11-17 12:00:00');

        foreach ([
            'Zeta' => '2026-11-07',
            'Biología' => '2026-11-12',
            'Álgebra' => '2026-11-12',
            'Alfa' => '2026-11-15',
        ] as $titulo => $prevista) {
            $libro = Libro::factory()->create(['titulo' => $titulo]);
            Prestamo::factory()->for($libro)->create([
                'fecha_devolucion_prevista' => $prevista,
                'fecha_devolucion_real' => null,
                'estado' => Prestamo::ESTADO_VENCIDO,
            ]);
        }

        $filas = $this->vencidosDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            [['Zeta', '10'], ['Álgebra', '5'], ['Biología', '5'], ['Alfa', '2']],
            array_map(fn (array $fila): array => [$fila[0], $fila[5]], $filas),
            'Orden por retraso mayor a menor con desempate alfabético insensible a tildes (D-020)'
        );
    }

    public function test_un_libro_con_varios_prestamos_pendientes_aparece_una_fila_por_prestamo(): void
    {
        $this->travelTo('2026-11-17 12:00:00');

        $libro = Libro::factory()->create(['titulo' => 'Préstamos múltiples']);

        foreach (['2026-11-15', '2026-11-13'] as $prevista) {
            Prestamo::factory()->for($libro)->create([
                'fecha_devolucion_prevista' => $prevista,
                'fecha_devolucion_real' => null,
                'estado' => Prestamo::ESTADO_VENCIDO,
            ]);
        }

        $filas = $this->vencidosDe($this->get('/panel')->assertOk()->getContent());

        $this->assertSame(
            [['Préstamos múltiples', '4'], ['Préstamos múltiples', '2']],
            array_map(fn (array $fila): array => [$fila[0], $fila[5]], $filas),
            'Cada préstamo pendiente genera su propia fila, aunque sea del mismo libro'
        );
    }
}
