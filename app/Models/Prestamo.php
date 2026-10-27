<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prestamo extends Model
{
    use HasFactory;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_DEVUELTO = 'devuelto';

    public const ESTADO_VENCIDO = 'vencido';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'libro_id',
        'nombre_estudiante',
        'carnet_estudiante',
        'fecha_prestamo',
        'fecha_devolucion_prevista',
        'fecha_devolucion_real',
        'estado',
    ];

    /**
     * Atributos con conversión de tipos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_prestamo' => 'date',
        'fecha_devolucion_prevista' => 'date',
        'fecha_devolucion_real' => 'date:Y-m-d',
    ];

    /**
     * Libro asociado al préstamo.
     *
     * @return BelongsTo<Libro, $this>
     */
    public function libro(): BelongsTo
    {
        return $this->belongsTo(Libro::class);
    }

    /**
     * Scope: préstamos en estado activo.
     *
     * @param  Builder<Prestamo>  $query
     * @return Builder<Prestamo>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }
}
