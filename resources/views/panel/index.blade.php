@extends('layouts.app')

@section('title', 'Panel de la biblioteca — Biblioteca Universitaria')

@section('content')
    <section class="panel">
        <h2>Panel de la biblioteca</h2>

        <section class="panel-seccion">
            <h3>Libros más prestados</h3>

            @if ($ranking->isEmpty())
                <p class="estado-vacio">No hay datos disponibles</p>
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
                <p class="estado-vacio">No hay datos disponibles</p>
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
                <p class="estado-vacio">No hay datos disponibles</p>
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

        <style>
            .panel > h2 {
                margin-top: 0;
                color: var(--color-primario);
            }

            .panel-seccion {
                background-color: #ffffff;
                border: 1px solid var(--color-borde);
                border-radius: 6px;
                margin-bottom: 1.5rem;
                overflow-x: auto;
            }

            .panel-seccion h3 {
                margin: 0;
                padding: 0.75rem 1rem;
                font-size: 1.1rem;
                color: var(--color-primario);
                border-bottom: 1px solid var(--color-borde);
            }

            .panel-seccion .estado-vacio {
                border: 0;
                border-radius: 0;
                margin: 0;
            }

            table.tabla-panel {
                width: 100%;
                border-collapse: collapse;
            }

            table.tabla-panel th,
            table.tabla-panel td {
                padding: 0.5rem 0.75rem;
                border-bottom: 1px solid var(--color-borde);
                text-align: left;
                white-space: nowrap;
            }

            table.tabla-panel th {
                font-size: 0.9rem;
                color: var(--color-primario);
            }

            table.tabla-panel tbody tr:last-child td {
                border-bottom: 0;
            }

            table.tabla-panel td.cifra {
                font-weight: bold;
                text-align: right;
            }

            table.tabla-panel td.posicion {
                color: #6c757d;
            }
        </style>
    </section>
@endsection
