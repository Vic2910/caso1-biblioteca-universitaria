<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Biblioteca Universitaria')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
    <header class="site-header">
        <h1>Biblioteca Universitaria</h1>

        <nav class="site-nav" aria-label="Navegación principal">
            <ul>
                <li>
                    <a href="{{ url('/') }}" @if (request()->is('/')) class="active" aria-current="page" @endif>Inicio</a>
                </li>
                <li>
                    <a href="{{ route('catalogo.index') }}" @if (request()->routeIs('catalogo.*')) class="active" aria-current="page" @endif>Catálogo</a>
                </li>
                <li>
                    <a href="{{ route('libros.index') }}" @if (request()->routeIs('libros.*')) class="active" aria-current="page" @endif>Libros</a>
                </li>
                <li>
                    <a href="{{ route('panel.index') }}" @if (request()->routeIs('panel.*')) class="active" aria-current="page" @endif>Panel</a>
                </li>
                <li>
                    <a href="{{ route('prestamos.index') }}" @if (request()->routeIs('prestamos.*')) class="active" aria-current="page" @endif>Préstamos</a>
                </li>
                <li>
                    <a href="{{ route('categorias.index') }}" @if (request()->routeIs('categorias.*')) class="active" aria-current="page" @endif>Categorías</a>
                </li>
            </ul>
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

    <main class="site-main">
        @if (session('success'))
            <div class="mensaje-exito" role="status" aria-live="polite">{{ session('success') }}</div>
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
