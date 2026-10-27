<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando la eliminación de un registro está bloqueada por la
 * existencia de registros relacionados (FR-006, user-messages.md §2).
 */
class RegistrosRelacionadosException extends RuntimeException
{
}
