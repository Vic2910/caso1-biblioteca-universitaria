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
                <p class="estado-vacio">No se encontraron libros que coincidan con la búsqueda</p>
            @else
                <p class="estado-vacio">No hay libros registrados aún</p>
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

        <style>
            form.barra-busqueda {
                display: flex;
                align-items: flex-end;
                flex-wrap: wrap;
                gap: 0.75rem;
                background-color: #ffffff;
                border: 1px solid var(--color-borde);
                border-radius: 6px;
                padding: 0.85rem 1rem;
                margin: 1rem 0 1.25rem;
            }

            form.barra-busqueda .campo-busqueda {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            form.barra-busqueda .campo-busqueda:first-child {
                flex: 1 1 18rem;
            }

            form.barra-busqueda label {
                font-weight: bold;
                font-size: 0.9rem;
                color: var(--color-primario);
            }

            form.barra-busqueda input[type="text"],
            form.barra-busqueda select {
                padding: 0.4rem 0.6rem;
                border: 1px solid var(--color-borde);
                border-radius: 4px;
                background-color: #ffffff;
                font: inherit;
            }

            form.barra-busqueda a.limpiar {
                margin-bottom: 0.1rem;
            }

            /* Se oculta visualmente conservando la semántica accesible:
               sin atributo hidden, para que el envío por «Enter» siga
               funcionando desde el campo de texto (006, T014). */
            form.barra-busqueda button.envio-oculto {
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

        <script src="{{ asset('js/busqueda-catalogo.js') }}" defer></script>
    </section>
@endsection
