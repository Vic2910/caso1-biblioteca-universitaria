<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fija la colación insensible a mayúsculas y acentos sobre libros.editorial,
     * el único campo de texto buscable que no la tenía garantizada (specs/
     * 006-busqueda-catalogo, D-030). Con ella, la comparación LIKE de la
     * búsqueda «coincidencia que contiene» ignora tildes y caso en los tres
     * campos de texto: titulo y autor ya lo fijó 002 (D-012) y isbn solo
     * contiene dígitos. La comparación usa la colación de la columna, por lo
     * que no hace falta SQL adicional en cada consulta (Principio I).
     *
     * Variantes para otros motores (D-030, data-model.md §Cambios de esquema):
     * MariaDB 10.11+ usa `utf8mb4_uca1400_ai_ci`; MySQL 5.7 u otros sin la
     * colación `utf8mb4_0900_ai_ci` caen a `utf8mb4_unicode_520_ci` — todos
     * comparan ignorando mayúsculas y acentos igual que el objetivo.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('libros', function (Blueprint $table) {
            $table->string('editorial')->collation($this->collationDisponible())->change();
        });
    }

    public function down(): void
    {
        // No se revierte: la colación original de la columna no está definida
        // (era el default del servidor, poco fiable según D-012) y quitar la
        // garantía volvería a dejar la búsqueda sensible a acentos y mayúsculas
        // en un campo que la especificación exige insensible (FR-003).
    }

    private function collationDisponible(): string
    {
        $candidatas = ['utf8mb4_0900_ai_ci', 'utf8mb4_uca1400_ai_ci', 'utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci'];

        $existentes = DB::select('SHOW COLLATION');

        $disponibles = array_map(fn ($collation) => $collation->Collation ?? '', $existentes);

        foreach ($candidatas as $candidata) {
            if (in_array($candidata, $disponibles, true)) {
                return $candidata;
            }
        }

        return 'utf8mb4_general_ci';
    }
};
