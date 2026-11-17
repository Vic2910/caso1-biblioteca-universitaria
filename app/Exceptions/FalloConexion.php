<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

/**
 * Detecta fallos de conexión a la base de datos (FR-008, research D-006):
 * acceso denegado, host inexistente o base de datos no disponible.
 *
 * Los códigos de error de PDO/MySQL se consultan en `errorInfo[1]`; los
 * mensajes de red (DNS, puerto) se reconocen por patrón cuando el driver no
 * informa de código de servidor.
 */
class FalloConexion
{
    /**
     * Códigos de error de PDO/MySQL que indican un fallo de conexión.
     *
     * 1040 demasiadas conexiones, 1044 acceso denegado a la base de datos,
     * 1045 acceso denegado (credenciales), 1049 base de datos desconocida,
     * 2002/2003 imposible conectarse, 2005 host desconocido,
     * 2013 pérdida de conexión durante la consulta.
     *
     * @var list<int>
     */
    private const CODIGOS_CONEXION = [1040, 1044, 1045, 1049, 2002, 2003, 2005, 2013];

    /**
     * Patrones de mensaje que describen fallos de red o de motor.
     *
     * @var list<string>
     */
    private const PATRONES_CONEXION = [
        'php_network_getaddresses',
        'getaddrinfo',
        'Connection refused',
        'Connection timed out',
        'No such file or directory',
        'server has gone away',
        'Access denied for user',
        'Unknown database',
        'unable to open database file',
    ];

    /**
     * ¿La excepción describe un fallo de conexión a la base de datos?
     */
    public static function detectar(Throwable $excepcion): bool
    {
        if ($excepcion instanceof QueryException || $excepcion instanceof PDOException) {
            $codigo = self::codigoDeServidor($excepcion);

            if ($codigo !== null && in_array($codigo, self::CODIGOS_CONEXION, true)) {
                return true;
            }

            $mensaje = $excepcion->getMessage();
            foreach (self::PATRONES_CONEXION as $patron) {
                if (str_contains($mensaje, $patron)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Código de error del servidor (errorInfo[1]) si estuviera disponible.
     */
    private static function codigoDeServidor(Throwable $excepcion): ?int
    {
        $pdo = $excepcion instanceof PDOException
            ? $excepcion
            : ($excepcion->getPrevious() instanceof PDOException ? $excepcion->getPrevious() : null);

        $codigo = $pdo?->errorInfo[1] ?? null;

        return is_int($codigo) ? $codigo : null;
    }
}
