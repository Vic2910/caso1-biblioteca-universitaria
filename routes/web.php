<?php

declare(strict_types=1);

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CategoriaController;
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
Route::delete('/libros/{libro}', [LibroController::class, 'destroy'])->whereNumber('libro')->name('libros.destroy');

/*
 * Gestión de categorías (specs/003-gestion-libros, US3, D-018).
 * REST propio bajo `/categorias`; la eliminación solo por DELETE.
 */
Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
Route::get('/categorias/crear', [CategoriaController::class, 'create'])->name('categorias.create');
Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
Route::get('/categorias/{categoria}/editar', [CategoriaController::class, 'edit'])->whereNumber('categoria')->name('categorias.edit');
Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->whereNumber('categoria')->name('categorias.update');
Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->whereNumber('categoria')->name('categorias.destroy');
