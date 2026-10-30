{{--
    Listado paginado de préstamos (specs/004-gestion-prestamos,
    contracts/ui-structure §2). Tabla con las 8 columnas en orden, badge de
    estado tras `sincronizarVencidos()` (D-020) y la acción «Registrar
    devolución» solo en filas activas o vencidas (FR-007: nunca hay edición
    ni eliminación). Estado vacío sin tabla ni paginación.
--}}
@extends('layouts.app')

@section('title', 'Préstamos — Biblioteca Universitaria')

@section('content')
    <h2>Préstamos registrados</h2>

    @if ($prestamos->isEmpty())
        <p class="estado-vacio">No hay préstamos registrados aún</p>
    @else
        <table class="tabla-gestion tabla-prestamos">
            <thead>
                <tr>
                    <th scope="col">Libro</th>
                    <th scope="col">Estudiante</th>
                    <th scope="col">Carné</th>
                    <th scope="col">Fecha de préstamo</th>
                    <th scope="col">Devolución prevista</th>
                    <th scope="col">Devolución real</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prestamos as $prestamo)
                    <tr>
                        <td>{{ $prestamo->libro->titulo }} ({{ $prestamo->libro->isbn }})</td>
                        <td>{{ $prestamo->nombre_estudiante }}</td>
                        <td>{{ $prestamo->carnet_estudiante }}</td>
                        <td>{{ $prestamo->fecha_prestamo->format('d/m/Y') }}</td>
                        <td>{{ $prestamo->fecha_devolucion_prevista->format('d/m/Y') }}</td>
                        <td>{{ $prestamo->fecha_devolucion_real?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            <span class="etiqueta-estado estado-{{ $prestamo->estado }}">{{ ucfirst($prestamo->estado) }}</span>
                        </td>
                        <td>
                            @unless ($prestamo->devuelto())
                                <a href="{{ route('prestamos.form-devolucion', $prestamo) }}" class="boton boton-secundario">
                                    Registrar devolución
                                </a>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $prestamos->links('pagination.prestamos') }}
    @endif

@endsection
