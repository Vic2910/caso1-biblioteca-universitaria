<?php

declare(strict_types=1);

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\LibroController;
use App\Http\Controllers\PortadaController;
use App\Http\Controllers\PrestamoController;
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
 * Entrega de portadas (specs/005-gestion-portadas, D-018/D-023).
 * SIN middleware `auth`: la comprobación de sesión es propia de la acción
 * para responder 401 con vista en español en lugar de redirigir (FR-007).
 * No se usa el enlace simbólico `/storage`; `php artisan storage:link` no
 * es requisito porque toda lectura pasa por esta ruta.
 */
Route::get('/portadas/{libro}', [PortadaController::class, 'show'])->whereNumber('libro')->name('portadas.show');

/*
 * Eliminación de portada (specs/005, FR-005/D-018): ruta nueva bajo
 * `/libros` SOLO para DELETE — GET/POST/PUT/PATCH responden 405 y no
 * eliminan nada (Principio IV); la confirmación es nativa y previa al
 * envío (SC-004, contracts/http-endpoints.md §2).
 */
Route::delete('/libros/{libro}/portada', [PortadaController::class, 'destroy'])->whereNumber('libro')->name('libros.portadas.destroy');

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

/*
 * Gestión de préstamos (specs/004-gestion-prestamos, D-018).
 * Solo registrar, consultar y devolver: NO existen rutas PUT/PATCH/DELETE
 * de préstamos porque el historial es inmutable (FR-007).
 */
Route::get('/prestamos', [PrestamoController::class, 'index'])->name('prestamos.index');
Route::get('/prestamos/crear', [PrestamoController::class, 'create'])->name('prestamos.create');
Route::post('/prestamos', [PrestamoController::class, 'store'])->name('prestamos.store');
Route::get('/prestamos/{id}/devolver', [PrestamoController::class, 'devolver'])->whereNumber('id')->name('prestamos.form-devolucion');
Route::post('/prestamos/{id}/devolver', [PrestamoController::class, 'registrarDevolucion'])->whereNumber('id')->name('prestamos.devolver');
