<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('libros', function (Blueprint $table) {
            $table->id();
            $table->char('isbn', 13)->unique();
            $table->string('titulo');
            $table->string('autor');
            $table->string('editorial');
            $table->smallInteger('anio_publicacion');
            $table->unsignedInteger('ejemplares_totales');
            $table->unsignedInteger('ejemplares_disponibles');
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->string('portada')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('libros');
    }
};
