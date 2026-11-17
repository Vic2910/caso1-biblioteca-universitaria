@extends('layouts.app')

@section('title', 'Biblioteca Universitaria — Inicio')

@section('content')
    <section class="inicio-cabecera">
        <h2>Bienvenido a la Biblioteca Universitaria</h2>
        <p>Consulte el catálogo, gestione libros y categorías, y registre préstamos y devoluciones.</p>

        <p>
            @auth
                <a class="boton-primario" href="{{ route('prestamos.create') }}">Registrar préstamo</a>
            @else
                <a class="boton-primario" href="{{ route('login') }}">Iniciar sesión</a>
            @endauth
        </p>
    </section>

    <section aria-labelledby="accesos-titulo">
        <h2 id="accesos-titulo">Secciones</h2>

        <ul class="rejilla-accesos">
            <li>
                <a class="tarjeta-acceso" href="{{ auth()->check() ? route('catalogo.index') : route('login') }}">
                    <span class="tarjeta-acceso-titulo">Catálogo</span>
                    <span>Buscar y consultar los libros disponibles.</span>
                </a>
            </li>
            <li>
                <a class="tarjeta-acceso" href="{{ auth()->check() ? route('libros.index') : route('login') }}">
                    <span class="tarjeta-acceso-titulo">Libros</span>
                    <span>Añadir, editar y gestionar portadas.</span>
                </a>
            </li>
            <li>
                <a class="tarjeta-acceso" href="{{ auth()->check() ? route('categorias.index') : route('login') }}">
                    <span class="tarjeta-acceso-titulo">Categorías</span>
                    <span>Organizar los libros por materia.</span>
                </a>
            </li>
            <li>
                <a class="tarjeta-acceso" href="{{ auth()->check() ? route('prestamos.index') : route('login') }}">
                    <span class="tarjeta-acceso-titulo">Préstamos</span>
                    <span>Registrar, consultar y devolver préstamos.</span>
                </a>
            </li>
            <li>
                <a class="tarjeta-acceso" href="{{ auth()->check() ? route('panel.index') : route('login') }}">
                    <span class="tarjeta-acceso-titulo">Panel</span>
                    <span>Indicadores de uso de la biblioteca.</span>
                </a>
            </li>
        </ul>
    </section>

    @auth
        <section aria-labelledby="estadisticas-titulo">
            <h2 id="estadisticas-titulo">Resumen</h2>
            <dl class="estadisticas">
                <div>
                    <dt>Libros registrados</dt>
                    <dd>{{ $totalLibros }}</dd>
                </div>
                <div>
                    <dt>Categorías</dt>
                    <dd>{{ $totalCategorias }}</dd>
                </div>
                <div>
                    <dt>Préstamos</dt>
                    <dd>{{ $totalPrestamos }}</dd>
                </div>
            </dl>
        </section>
    @endauth
@endsection
