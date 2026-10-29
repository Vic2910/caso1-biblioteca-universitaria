<?php

declare(strict_types=1);

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\LibroController;
use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'totalLibros' => Libro::query()->count(),
        'totalCategorias' => Categoria::query()->count(),
        'totalPrestamos' => Prestamo::query()->count(),
    ]);
});

Route::get('/catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
Route::get('/catalogo/{id}', [CatalogoController::class, 'show'])->whereNumber('id')->name('catalogo.show');

/*
 * Gestión de libros (specs/003-gestion-libros, D-018).
 * Las rutas de consulta de `002` quedan intactas; la eliminación (DELETE)
 * se registra en la fase de US2.
 */
Route::get('/libros/crear', [LibroController::class, 'create'])->name('libros.create');
Route::post('/libros', [LibroController::class, 'store'])->name('libros.store');
Route::get('/libros/{libro}/editar', [LibroController::class, 'edit'])->whereNumber('libro')->name('libros.edit');
Route::put('/libros/{libro}', [LibroController::class, 'update'])->whereNumber('libro')->name('libros.update');
