@extends('layouts.app')

@section('title', 'Panel de la biblioteca — Biblioteca Universitaria')

@section('content')
    <section class="panel">
        <h2>Panel de la biblioteca</h2>

        <section class="panel-seccion">
            <h3>Libros más prestados</h3>

            <p><a class="enlace-accion" href="{{ route('catalogo.index') }}">Ir al catálogo</a></p>

    <details>
        <summary>Cómo leer los indicadores</summary>
        <p>Cada bloque muestra su unidad de medida en texto, por ejemplo préstamos o ejemplares disponibles. Los datos son de solo consulta.</p>
    </details>

    @if ($ranking->isEmpty())
                @include('partials.estado-vacio', ['mensaje' => 'No hay datos disponibles'])
            @else
                <table class="tabla-panel">
                    <thead>
                        <tr>
                            <th>N.º</th>
                            <th>Título</th>
                            <th>Autor</th>
                            <th>Préstamos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ranking as $libro)
                            <tr>
                                <td class="posicion">{{ $loop->iteration }}</td>
                                <td>{{ $libro['titulo'] }}</td>
                                <td>{{ $libro['autor'] }}</td>
                                <td class="cifra">{{ $libro['prestamos'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="panel-seccion">
            <h3>Préstamos vencidos</h3>

            @if ($vencidos->isEmpty())
                @include('partials.estado-vacio', ['mensaje' => 'No hay datos disponibles'])
            @else
                <table class="tabla-panel">
                    <thead>
                        <tr>
                            <th>Libro</th>
                            <th>Estudiante</th>
                            <th>Carné</th>
                            <th>Fecha de préstamo</th>
                            <th>Devolución prevista</th>
                            <th>Días de retraso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vencidos as $prestamo)
                            <tr>
                                <td>{{ $prestamo['libro'] }}</td>
                                <td>{{ $prestamo['estudiante'] }}</td>
                                <td>{{ $prestamo['carnet'] }}</td>
                                <td>{{ $prestamo['fecha_prestamo'] }}</td>
                                <td>{{ $prestamo['fecha_devolucion_prevista'] }}</td>
                                <td class="cifra">{{ $prestamo['dias_retraso'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="panel-seccion">
            <h3>Ejemplares disponibles por categoría</h3>

            @if ($disponibilidad->isEmpty())
                @include('partials.estado-vacio', ['mensaje' => 'No hay datos disponibles'])
            @else
                <table class="tabla-panel">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Ejemplares disponibles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($disponibilidad as $categoria)
                            <tr>
                                <td>{{ $categoria['nombre'] }}</td>
                                <td class="cifra">{{ $categoria['disponibles'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

    </section>
@endsection
