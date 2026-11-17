<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory;

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
    ];

    /**
     * Reglas del modelo: ningún guardado deja espacios sobrantes en el nombre.
     * La validación ya recorta en `prepareForValidation` (primera capa); aquí
     * se vuelve a recortar al persistir para que ninguna otra vía de escritura
     * (tinker, seeds, importaciones) guarde un nombre con espacios que rompería
     * la unicidad insensible a espacios del collation (specs/003, D-020).
     */
    protected static function booted(): void
    {
        static::saving(function (Categoria $categoria): void {
            $categoria->nombre = trim((string) $categoria->nombre);
        });
    }

    /**
     * Libros pertenecientes a esta categoría.
     *
     * @return HasMany<Libro, $this>
     */
    public function libros(): HasMany
    {
        return $this->hasMany(Libro::class);
    }
}
