@extends('layouts.app')

@section('title', 'Panel de la biblioteca — Biblioteca Universitaria')

@section('content')
    <section class="panel">
        <div class="cabecera-seccion">
            <h2>Panel de la biblioteca</h2>
            <a class="enlace-accion" href="{{ route('catalogo.index') }}">Ir al catálogo</a>
        </div>

        <details>
            <summary>Cómo leer los indicadores</summary>
            <p>Cada bloque muestra su unidad de medida en texto, por ejemplo préstamos o ejemplares disponibles. Los datos son de solo consulta.</p>
        </details>

        <section class="panel-seccion">
            <h3>Libros más prestados</h3>

            @if ($ranking->isEmpty())
                @include('partials.estado-vacio', ['mensaje' => 'No hay datos disponibles'])
            @else
                <div class="zona-gestion">
                    <table class="tabla-gestion tabla-panel">
                        <thead>
                            <tr>
                                <th scope="col">N.º</th>
                                <th scope="col">Título</th>
                                <th scope="col">Autor</th>
                                <th scope="col">Préstamos</th>
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
                </div>
            @endif
        </section>

        <section class="panel-seccion">
            <h3>Préstamos vencidos</h3>

            @if ($vencidos->isEmpty())
                @include('partials.estado-vacio', ['mensaje' => 'No hay datos disponibles'])
            @else
                <div class="zona-gestion">
                    <table class="tabla-gestion tabla-panel">
                        <thead>
                            <tr>
                                <th scope="col">Libro</th>
                                <th scope="col">Estudiante</th>
                                <th scope="col">Carné</th>
                                <th scope="col">Fecha de préstamo</th>
                                <th scope="col">Devolución prevista</th>
                                <th scope="col">Días de retraso</th>
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
                </div>
            @endif
        </section>

        <section class="panel-seccion">
            <h3>Ejemplares disponibles por categoría</h3>

            @if ($disponibilidad->isEmpty())
                @include('partials.estado-vacio', ['mensaje' => 'No hay datos disponibles'])
            @else
                <div class="zona-gestion">
                    <table class="tabla-gestion tabla-panel">
                        <thead>
                            <tr>
                                <th scope="col">Categoría</th>
                                <th scope="col">Ejemplares disponibles</th>
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
                </div>
            @endif
        </section>
    </section>
@endsection
