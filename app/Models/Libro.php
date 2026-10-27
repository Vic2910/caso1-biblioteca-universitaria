<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Libro extends Model
{
    use HasFactory;

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'isbn',
        'titulo',
        'autor',
        'editorial',
        'anio_publicacion',
        'ejemplares_totales',
        'ejemplares_disponibles',
        'categoria_id',
        'portada',
    ];

    /**
     * Atributos con conversión de tipos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'anio_publicacion' => 'integer',
        'ejemplares_totales' => 'integer',
        'ejemplares_disponibles' => 'integer',
    ];

    /**
     * Categoría a la que pertenece el libro.
     *
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Préstamos registrados para este libro.
     *
     * @return HasMany<Prestamo, $this>
     */
    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class);
    }
}
