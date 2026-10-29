@extends('layouts.app')

@section('title', 'Ficha del libro — Biblioteca Universitaria')

@section('content')
    <section class="ficha">
        <h2>Ficha del libro</h2>

        @include('partials.portada', ['portada' => $libro->portada, 'titulo' => $libro->titulo])

        <dl class="ficha-campos">
            <dt>Título</dt>
            <dd>{{ $libro->titulo }}</dd>

            <dt>Autor</dt>
            <dd>{{ $libro->autor }}</dd>

            <dt>Editorial</dt>
            <dd>{{ $libro->editorial }}</dd>

            <dt>Año de publicación</dt>
            <dd>{{ $libro->anio_publicacion }}</dd>

            <dt>ISBN</dt>
            <dd>{{ $libro->isbn }}</dd>

            <dt>Total de ejemplares</dt>
            <dd>{{ $libro->ejemplares_totales }}</dd>

            <dt>Ejemplares disponibles</dt>
            <dd>{{ $libro->ejemplares_disponibles }}</dd>

            <dt>Categoría</dt>
            <dd>{{ $libro->categoria->nombre }}</dd>
        </dl>

        <p>
            <a href="{{ route('catalogo.index') }}">Volver al catálogo</a>
        </p>
    </section>
@endsection
