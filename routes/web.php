<?php

declare(strict_types=1);

use App\Http\Controllers\CatalogoController;
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
