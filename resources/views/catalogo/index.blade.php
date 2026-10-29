@extends('layouts.app')

@section('title', 'Catálogo de libros — Biblioteca Universitaria')

@section('content')
    <section class="catalogo">
        <div class="cabecera-seccion">
            <h2>Catálogo de libros</h2>
            <a class="boton" href="{{ route('libros.create') }}">Nuevo libro</a>
        </div>

        @if ($libros->total() === 0)
            <p class="estado-vacio">No hay libros registrados aún</p>
        @else
            <ul class="rejilla-libros">
                @foreach ($libros as $libro)
                    <li>
                        <a class="tarjeta-libro" href="{{ route('catalogo.show', $libro) }}">
                            @include('partials.portada', ['portada' => $libro->portada, 'titulo' => $libro->titulo])
                            <h3>{{ $libro->titulo }}</h3>
                            <p class="autor">{{ $libro->autor }}</p>
                            <p class="anio">{{ $libro->anio_publicacion }}</p>
                            <p class="categoria">{{ $libro->categoria->nombre }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{ $libros->links('pagination.catalogo') }}
        @endif
    </section>
@endsection
