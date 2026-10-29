<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Habilita la comparación insensible a acentos y mayúsculas en categorias.nombre,
     * igual que libros.isbn. Sin esto, la regla unique de Laravel no detecta que
     * «Matemáticas» y «MATEMATICAS» son la misma categoría y la base de datos tampoco
     * genera un error al insertarlas. La validación rechaza la duplicada; este índice
     * es la garantía de que no puede colarse por otra vía.
     *
     * La regla unique es la primera capa de protección en cada alta y edición. Este
     * índice es la última por si esa validación se evade, y es la única que se activa
     * si alguien inserta por fuera de la aplicación.
     *
     * Variantes para otros motores (D-020, data-model.md §Cambios de esquema):
     * MariaDB 10.11+ usa `utf8mb4_uca1400_ai_ci`; MySQL 5.7 u otros sin el collation
     * `utf8mb4_0900_ai_ci` caen a `utf8mb4_unicode_520_ci` — ambos comparan
     * ignorando mayúsculas y acentos igual que el objetivo.
     *
     * No es idempotente porque MySQL 8 no permite leer el collation de una columna
     * desde INFORMATION_SCHEMA y usarlo en el mismo ALTER. Si se ejecuta dos veces,
     * el segundo cambio es un no-op: se vuelve a fijar el collation objetivo.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('categorias', function (Blueprint $table) {
            $table->string('nombre', 100)->collation($this->collationDisponible())->change();
        });
    }

    public function down(): void
    {
        // No se revierte: quitar el collation insensible a acentos volvería a dejar
        // la base de datos sin la garantía de unicidad y no hay una «versión
        // sensible» uniforme que aplicar a un índice ya existente.
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
