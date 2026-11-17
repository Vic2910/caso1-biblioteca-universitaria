<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Biblioteca Universitaria')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @auth data-sesion-expira="{{ now()->addMinutes(config('session.lifetime'))->timestamp }}" @endauth>
    <a class="enlace-saltar" href="#contenido">Saltar al contenido principal</a>

    <header class="site-header">
        <h1>Biblioteca Universitaria</h1>

        <nav class="site-nav" aria-label="Navegación principal">
            <ul class="menu-horizontal">
                <li>
                    <a href="{{ url('/') }}" @if (request()->is('/')) class="active" aria-current="page" @endif>Inicio</a>
                </li>
                <li>
                    <a href="{{ route('catalogo.index') }}" @if (request()->routeIs('catalogo.*')) class="active" aria-current="page" @endif>Catálogo</a>
                </li>
                <li>
                    <details class="menu-desplegable" data-menu>
                        <summary @if (request()->routeIs('libros.*', 'categorias.*', 'prestamos.*')) class="active" @endif>Gestión</summary>
                        <ul class="menu-desplegable-lista">
                            <li><a href="{{ route('libros.index') }}" @if (request()->routeIs('libros.*')) aria-current="page" @endif>Libros</a></li>
                            <li><a href="{{ route('categorias.index') }}" @if (request()->routeIs('categorias.*')) aria-current="page" @endif>Categorías</a></li>
                            <li><a href="{{ route('prestamos.index') }}" @if (request()->routeIs('prestamos.*')) aria-current="page" @endif>Préstamos</a></li>
                        </ul>
                    </details>
                </li>
                <li>
                    <a href="{{ route('panel.index') }}" @if (request()->routeIs('panel.*')) class="active" aria-current="page" @endif>Panel</a>
                </li>
            </ul>

            <details class="menu-hamburguesa" data-menu>
                <summary>Menú principal</summary>
                <ul>
                    <li><a href="{{ url('/') }}" @if (request()->is('/')) aria-current="page" @endif>Inicio</a></li>
                    <li><a href="{{ route('catalogo.index') }}" @if (request()->routeIs('catalogo.*')) aria-current="page" @endif>Catálogo</a></li>
                    <li><a href="{{ route('libros.index') }}" @if (request()->routeIs('libros.*')) aria-current="page" @endif>Libros</a></li>
                    <li><a href="{{ route('categorias.index') }}" @if (request()->routeIs('categorias.*')) aria-current="page" @endif>Categorías</a></li>
                    <li><a href="{{ route('prestamos.index') }}" @if (request()->routeIs('prestamos.*')) aria-current="page" @endif>Préstamos</a></li>
                    <li><a href="{{ route('panel.index') }}" @if (request()->routeIs('panel.*')) aria-current="page" @endif>Panel</a></li>
                </ul>
            </details>
        </nav>

        <div class="sesion-usuario">
            @auth
                <span>Hola, {{ Auth::user()->name }}</span>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="boton-sesion" type="submit">Cerrar sesión</button>
                </form>
            @else
                <a href="{{ route('login') }}">Iniciar sesión</a>
            @endauth
        </div>
    </header>

    @auth
        <div id="aviso-sesion" class="aviso-sesion" role="alertdialog" aria-labelledby="aviso-sesion-texto" hidden>
            <p id="aviso-sesion-texto">Quedan 5 minutos antes de que finalice su sesión.</p>
            <div class="aviso-sesion-acciones">
                <button id="aviso-sesion-extender" type="button" class="boton-primario">Extender sesión</button>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="boton-secundario" type="submit">Cerrar sesión</button>
                </form>
            </div>
            <form id="formulario-extender-sesion" method="post" action="{{ route('sesion.extender') }}" hidden>
                @csrf
            </form>
        </div>
    @endauth

    <main id="contenido" class="site-main" tabindex="-1">
        @if (session('success'))
            @include('partials.toast', ['mensaje' => session('success')])
        @endif

        @if (session('error'))
            <div class="mensaje-error" role="alert">{{ session('error') }}</div>
        @endif

        @error('csrf')
            <div class="mensaje-error" role="alert">
                <p id="error-csrf">{{ $message }}</p>
            </div>
        @enderror

        @if ($errors->any())
            <div class="mensaje-error" role="alert">
                <p>Se han detectado errores en el formulario:</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="site-footer">
        Biblioteca Universitaria &mdash; Interfaz común y validación segura
    </footer>
</body>
</html>
