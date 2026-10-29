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
     * `ejemplares_disponibles` queda fuera a propósito: es un valor derivado
     * que solo calcula el servidor (specs/003-gestion-libros, D-023, FR-007).
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
        'categoria_id',
        'portada',
    ];

    /**
     * Reglas del modelo: ningún guardado deja el invariante de disponibilidad
     * roto (specs/003-gestion-libros, D-023, Principio III).
     */
    protected static function booted(): void
    {
        static::saving(function (Libro $libro): void {
            $libro->recalcularDisponibilidad();
        });
    }

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
     * Número de préstamos activos registrados para este libro.
     */
    public function prestamosActivos(): int
    {
        return $this->prestamos()->where('estado', Prestamo::ESTADO_ACTIVO)->count();
    }

    /**
     * Recalcula la disponibilidad derivada: totales − préstamos activos.
     * Se ejecuta en el evento `saving`, de modo que todo camino de escritura
     * de la aplicación mantiene el invariante (specs/003, D-023, FR-007).
     */
    public function recalcularDisponibilidad(): void
    {
        $this->ejemplares_disponibles = max(0, $this->ejemplares_totales - $this->prestamosActivos());
    }

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
