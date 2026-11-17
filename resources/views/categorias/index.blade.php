@extends('layouts.app')

@section('title', 'Categorías — Biblioteca Universitaria')

@section('content')
    <section class="gestion-categorias">
        <div class="cabecera-seccion">
            <h2>Categorías</h2>
            <a class="boton" href="{{ route('categorias.create') }}">Nueva categoría</a>
        </div>

        @if ($categorias->isEmpty())
            @include('partials.estado-vacio', [
                'mensaje' => 'No hay registros',
                'accion' => 'Nueva categoría',
                'url' => route('categorias.create'),
            ])
        @else
            <table class="tabla-gestion">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Libros asociados</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categorias as $categoria)
                        <tr>
                            <td>{{ $categoria->nombre }}</td>
                            <td>{{ $categoria->libros_count }}</td>
                            <td class="acciones-fila">
                                <a class="boton boton-secundario"
                                   href="{{ route('categorias.edit', $categoria) }}">Editar</a>
                                <form method="post" action="{{ route('categorias.destroy', $categoria) }}" onsubmit="return confirm('¿Eliminar la categoría «{{ $categoria->nombre }}»? Esta acción no se puede deshacer.')" data-confirm="¿Está seguro de que desea eliminar este registro?">
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
