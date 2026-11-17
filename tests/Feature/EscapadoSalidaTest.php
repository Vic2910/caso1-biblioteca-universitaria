<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V-007 y SC-003: toda salida de datos se escapa con `{{ }}`. La auditoría
 * prohíbe `{!! !!}` fuera del HTML generado por Laravel y el caso funcional
 * comprueba que el contenido hostil se ve como texto (FR-005/FR-006).
 */
class EscapadoSalidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_hay_eco_sin_escapar_en_las_vistas(): void
    {
        $directorio = resource_path('views');
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directorio));
        $hallazgos = [];

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile()) {
                continue;
            }

            $contenido = (string) file_get_contents($archivo->getPathname());

            if (str_contains($contenido, '{!!') || str_contains($contenido, '!!}')) {
                $hallazgos[] = str_replace($directorio.DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            }
        }

        $this->assertSame([], $hallazgos, 'Las vistas no deben usar salida sin escapar con {!! !!}.');
    }

    public function test_un_titulo_con_script_y_un_sql_inyeccion_se_muestran_como_texto(): void
    {
        $tituloScript = "<script>alert('x')</script>";
        $tituloSql = "' OR 1=1 --";

        Libro::factory()->create(['titulo' => $tituloScript]);
        Libro::factory()->create(['titulo' => $tituloSql]);

        $respuesta = $this->get(route('catalogo.index'))->assertOk();
        $html = (string) $respuesta->getContent();

        $this->assertStringContainsString(e($tituloScript), $html);
        $this->assertStringContainsString(e($tituloSql), $html);
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString($tituloSql, $html);
        $this->assertDatabaseCount('libros', 2);
    }
}
