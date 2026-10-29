<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cubre el escenario V-005 (fallo de conexión) y el contrato
 * specs/001-entorno-datos/contracts/user-messages.md §1:
 * ante `PDOException`/`QueryException` la aplicación muestra un aviso en
 * español, no sirve ninguna vista de datos y no expone credenciales ni SQL.
 */
class FalloConexionTest extends TestCase
{
    public function test_aviso_en_espanol_cuando_la_base_de_datos_no_existe(): void
    {
        config(['database.connections.mysql.database' => 'biblioteca_inexistente_zz']);
        DB::purge('mysql');

        $respuesta = $this->get('/');

        $respuesta->assertStatus(500);
        $respuesta->assertViewIs('errors.conexion');
        $respuesta->assertSee('no se pudo conectar con la base de datos', false);
        $respuesta->assertSee('revise la configuración de conexión en el archivo', false);

        $this->assertNoExponeCredencialesNiSql($respuesta->getContent());
        $this->assertNoSirveVistaDeDatos($respuesta->getContent());
    }

    public function test_aviso_en_espanol_cuando_el_acceso_esta_denegado(): void
    {
        config(['database.connections.mysql.username' => 'usuario_inexistente_zz']);
        config(['database.connections.mysql.password' => 'clave_inexistente_zz']);
        DB::purge('mysql');

        $respuesta = $this->get('/');

        $respuesta->assertStatus(500);
        $respuesta->assertViewIs('errors.conexion');
        $respuesta->assertSee('no se pudo conectar con la base de datos', false);

        $contenido = $respuesta->getContent();
        $this->assertNoExponeCredencialesNiSql($contenido);
        $this->assertStringNotContainsString('usuario_inexistente_zz', $contenido);
        $this->assertStringNotContainsString('clave_inexistente_zz', $contenido);
        $this->assertNoSirveVistaDeDatos($contenido);
    }

    public function test_aviso_en_espanol_cuando_el_host_no_existe(): void
    {
        config(['database.connections.mysql.host' => 'host.inexistente.invalid']);
        DB::purge('mysql');

        $respuesta = $this->get('/');

        $respuesta->assertStatus(500);
        $respuesta->assertViewIs('errors.conexion');
        $respuesta->assertSee('no se pudo conectar con la base de datos', false);

        $this->assertNoExponeCredencialesNiSql($respuesta->getContent());
        $this->assertNoSirveVistaDeDatos($respuesta->getContent());
    }

    /**
     * SC-004: el aviso jamás contiene usuario, contraseña, host ni SQL.
     */
    private function assertNoExponeCredencialesNiSql(string $contenido): void
    {
        $conexion = config('database.connections.mysql');

        $this->assertStringNotContainsString((string) $conexion['username'], $contenido);

        $password = (string) ($conexion['password'] ?? '');
        if ($password !== '') {
            $this->assertStringNotContainsString($password, $contenido);
        }

        $this->assertStringNotContainsString((string) $conexion['host'], $contenido);
        $this->assertStringNotContainsString('SQLSTATE', $contenido);
        $this->assertStringNotContainsString('SELECT', $contenido);
        $this->assertStringNotContainsString('select', $contenido);
    }

    /**
     * La aplicación no continúa: ninguna vista de datos se sirve.
     *
     * El marcado de tarjetas del catálogo sustituye a la palabra «Catálogo»
     * como centinela: desde 002-consulta-catalogo el enlace de navegación
     * «Catálogo» forma parte del layout común y aparece también en las vistas
     * de error (FR-004), sin que por ello se sirva ninguna vista de datos.
     */
    private function assertNoSirveVistaDeDatos(string $contenido): void
    {
        $this->assertStringNotContainsString('Conexión a la base de datos verificada', $contenido);
        $this->assertStringNotContainsString('<a class="tarjeta-libro"', $contenido);
        $this->assertStringNotContainsString('No hay libros registrados aún', $contenido);
    }
}
