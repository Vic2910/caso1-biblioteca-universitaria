<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RegistrosRelacionadosException;
use App\Models\Categoria;
use App\Models\Libro;

/**
 * Eliminación de registros relacionados (FR-006, SC-005, data-model.md
 * §Reglas de eliminación documentadas): comprobación previa en la aplicación
 * con aviso en español; las claves foráneas `restrictOnDelete()` (D-004)
 * permanecen como red de seguridad en la base de datos.
 */
class EliminacionService
{
    /**
     * Elimina una categoría mientras no tenga libros asociados.
     *
     * @throws RegistrosRelacionadosException si la categoría tiene libros.
     */
    public function eliminarCategoria(Categoria $categoria): void
    {
        if ($categoria->libros()->exists()) {
            throw new RegistrosRelacionadosException(
                'La categoría tiene libros asociados y no puede eliminarse mientras los tenga.'
            );
        }

        $categoria->delete();
    }

    /**
     * Elimina un libro mientras no tenga préstamos asociados.
     *
     * @throws RegistrosRelacionadosException si el libro tiene préstamos.
     */
    public function eliminarLibro(Libro $libro): void
    {
        if ($libro->prestamos()->exists()) {
            throw new RegistrosRelacionadosException(
                'El libro tiene préstamos asociados y no puede eliminarse mientras los tenga.'
            );
        }

        $libro->delete();
    }
}
