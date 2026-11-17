@extends('layouts.app')

@section('title', 'Catálogo de libros — Biblioteca Universitaria')

@section('content')
    <section class="catalogo">
        <div class="cabecera-seccion">
            <h2>Catálogo de libros</h2>
            <a class="boton" href="{{ route('libros.create') }}">Nuevo libro</a>
        </div>

        {{--
            Barra de búsqueda sobre la ruta existente (006, D-028): GET con
            `q` y `categoria`, sin `page` para que cada criterio reinicie la
            paginación (D-034). El botón se oculta con CSS —nunca con el
            atributo hidden— para que «Enter» envíe el formulario.
        --}}
        {{-- Regla local: el botón existe para «Enter» pero no ocupa espacio visual (T014). --}}
        <style>
            .envio-oculto {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }
        </style>

        <form id="busqueda" class="barra-busqueda" method="get" action="{{ route('catalogo.index') }}">
            <div class="campo-busqueda">
                <label for="q">Buscar</label>
                <input
                    type="text"
                    id="q"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Buscar por título, autor, editorial o ISBN"
                    autocomplete="off"
                >
            </div>

            <div class="campo-busqueda">
                <label for="categoria">Categoría</label>
                <select name="categoria" id="categoria">
                    <option value="">Todas las categorías</option>
                    @foreach ($categorias as $opcion)
                        <option value="{{ $opcion->id }}" @selected($categoria === $opcion->id)>{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="envio-oculto">Buscar</button>

            @if ($hayCriterio)
                <a class="boton boton-secundario limpiar" href="{{ route('catalogo.index') }}">Limpiar</a>
            @endif
        </form>

        @if ($libros->total() === 0)
            @if ($hayCriterio)
                @include('partials.estado-vacio', ['mensaje' => 'No se encontraron libros que coincidan con la búsqueda'])
            @else
                @include('partials.estado-vacio', ['mensaje' => 'No hay libros registrados aún'])
            @endif
        @else
            <ul class="rejilla-libros">
                @foreach ($libros as $libro)
                    <li>
                        <a class="tarjeta-libro" href="{{ route('catalogo.show', $libro) }}">
                            @include('partials.portada', ['libro' => $libro])
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

        <script src="{{ asset('js/busqueda-catalogo.js') }}" defer></script>
    </section>
@endsection
