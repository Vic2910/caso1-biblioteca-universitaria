<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colación accent-insensitive y case-insensitive fijada para el orden
     * alfabético en español del catálogo (specs/002-consulta-catalogo, D-012).
     * MySQL 8 usa utf8mb4_0900_ai_ci (UCA 9.0.0); MariaDB, que no la ofrece,
     * usa la variante portable utf8mb4_unicode_520_ci (UCA 5.2.0).
     */
    private const COLLATIONS_PREFERENTES = [
        'utf8mb4_0900_ai_ci',
        'utf8mb4_unicode_520_ci',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $collation = $this->collationDisponible();

        Schema::table('libros', function (Blueprint $table) use ($collation) {
            $table->string('titulo')->collation($collation)->change();
            $table->string('autor')->collation($collation)->change();
        });
    }

    /**
     * Reverse the migrations: restaura la colación por defecto de la columna.
     */
    public function down(): void
    {
        $collationPorDefecto = $this->collationActualDeColumna('titulo');

        Schema::table('libros', function (Blueprint $table) use ($collationPorDefecto) {
            $table->string('titulo')->collation($collationPorDefecto)->change();
            $table->string('autor')->collation($collationPorDefecto)->change();
        });
    }

    /**
     * Elige la primera colación preferente soportada por el motor conectado.
     */
    private function collationDisponible(): string
    {
        $soportadas = DB::select(
            'SELECT COLLATION_NAME AS nombre
             FROM information_schema.COLLATIONS
             WHERE CHARACTER_SET_NAME = ?',
            ['utf8mb4']
        );

        $nombres = array_map(fn (object $fila): string => $fila->nombre, $soportadas);

        foreach (self::COLLATIONS_PREFERENTES as $candidata) {
            if (in_array($candidata, $nombres, true)) {
                return $candidata;
            }
        }

        throw new RuntimeException(
            'Ninguna colación utf8mb4 insensible a acentos y mayúsculas está disponible en este motor.'
        );
    }

    /**
     * Consulta la colación actual de una columna de libros en information_schema.
     */
    private function collationActualDeColumna(string $columna): string
    {
        return DB::selectOne(
            'SELECT COLLATION_NAME AS collation
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            ['libros', $columna]
        )->collation;
    }
};
