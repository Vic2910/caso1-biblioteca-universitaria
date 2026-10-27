<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Cubre el escenario V-007 (credenciales fuera del repositorio, FR-007)
 * y el contrato specs/001-entorno-datos/contracts/environment-variables.md.
 */
class CredencialesTest extends TestCase
{
    public function test_archivo_env_no_esta_versionado_en_git(): void
    {
        $resultado = Process::run('git ls-files');

        $this->assertSame(0, $resultado->exitCode(), 'No se pudo consultar git ls-files');

        $archivos = array_values(array_filter(
            explode(PHP_EOL, $resultado->output()),
            fn (string $ruta): bool => $ruta !== ''
        ));

        $this->assertNotContains('.env', $archivos, 'El archivo .env no debe estar versionado');
        $this->assertNotContains('biblioteca-universitaria/.env', $archivos, 'El archivo .env no debe estar versionado');
    }

    public function test_env_example_si_esta_versionado(): void
    {
        $resultado = Process::run('git ls-files');

        $this->assertSame(0, $resultado->exitCode());
        $this->assertStringContainsString('.env.example', $resultado->output());
    }

    public function test_env_example_define_laconexion_sin_credenciales_reales(): void
    {
        $contenido = file_get_contents(base_path('.env.example'));

        $this->assertNotFalse($contenido, 'Debe existir .env.example');
        $this->assertMatchesRegularExpression('/^DB_CONNECTION=mysql$/m', $contenido);
        $this->assertMatchesRegularExpression('/^DB_HOST=127\.0\.0\.1$/m', $contenido);
        $this->assertMatchesRegularExpression('/^DB_PORT=3306$/m', $contenido);
        $this->assertMatchesRegularExpression('/^DB_DATABASE=biblioteca$/m', $contenido);
        $this->assertMatchesRegularExpression('/^DB_USERNAME=root$/m', $contenido);

        $this->assertMatchesRegularExpression(
            '/^DB_PASSWORD=$/m',
            $contenido,
            'DB_PASSWORD debe estar vacío en .env.example (nunca versionado)'
        );
        $this->assertMatchesRegularExpression(
            '/^APP_KEY=$/m',
            $contenido,
            'APP_KEY no debe llevar valor real en .env.example'
        );
    }
}
