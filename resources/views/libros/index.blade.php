@extends('layouts.app')

@section('title', 'Libros — Biblioteca Universitaria')

@section('content')
    <section class="gestion-libros">
        <div class="cabecera-seccion">
            <h2>Libros registrados</h2>
            <a class="boton" href="{{ route('libros.create') }}">Añadir libro</a>
        </div>

        @if ($libros->isEmpty())
            @include('partials.estado-vacio', [
                'mensaje' => 'No hay registros',
                'accion' => 'Añadir libro',
                'url' => route('libros.create'),
            ])
        @else
            <table class="tabla-gestion">
                <thead>
                    <tr>
                        <th scope="col">Título</th>
                        <th scope="col">Autor</th>
                        <th scope="col">ISBN</th>
                        <th scope="col">Categoría</th>
                        <th scope="col">Ejemplares</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($libros as $libro)
                        <tr>
                            <td>{{ $libro->titulo }}</td>
                            <td>{{ $libro->autor }}</td>
                            <td>{{ $libro->isbn }}</td>
                            <td>{{ $libro->categoria?->nombre ?? '—' }}</td>
                            <td>{{ $libro->ejemplares_totales }}</td>
                            <td class="acciones-fila">
                                <a class="boton boton-secundario" href="{{ route('catalogo.show', $libro) }}">Ver</a>
                                <a class="boton boton-secundario" href="{{ route('libros.edit', $libro) }}">Editar</a>
                                <form method="post"
                                      action="{{ route('libros.destroy', $libro) }}"
                                      data-confirm="¿Está seguro de que desea eliminar este registro?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="boton boton-peligro">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
