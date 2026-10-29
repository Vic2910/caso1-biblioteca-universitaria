<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Biblioteca Universitaria')</title>
    <style>
        html {
            --color-primario: #1f4e79;
            --color-fondo: #f5f7fa;
            --color-texto: #212529;
            --color-borde: #dee2e6;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, 'Times New Roman', serif;
            background-color: var(--color-fondo);
            color: var(--color-texto);
            line-height: 1.6;
        }

        header.site-header {
            background-color: var(--color-primario);
            color: #ffffff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem 1.5rem;
        }

        header.site-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: normal;
            letter-spacing: 0.04em;
        }

        nav.site-nav ul {
            list-style: none;
            display: flex;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
        }

        nav.site-nav a {
            display: inline-block;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
        }

        nav.site-nav a:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        nav.site-nav a.active {
            background-color: #ffffff;
            color: var(--color-primario);
            font-weight: bold;
        }

        main.site-main {
            max-width: 960px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        footer.site-footer {
            text-align: center;
            padding: 1.5rem;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .aviso {
            border: 1px solid var(--color-borde);
            border-left: 4px solid var(--color-primario);
            background-color: #ffffff;
            padding: 1rem 1.25rem;
            border-radius: 4px;
        }

        .catalogo h2,
        .ficha h2 {
            margin-top: 0;
            color: var(--color-primario);
        }

        .estado-vacio {
            border: 1px dashed var(--color-borde);
            background-color: #ffffff;
            padding: 2rem;
            text-align: center;
            color: #6c757d;
            border-radius: 4px;
        }

        ul.rejilla-libros {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.25rem;
        }

        a.tarjeta-libro {
            display: flex;
            flex-direction: column;
            height: 100%;
            box-sizing: border-box;
            background-color: #ffffff;
            border: 1px solid var(--color-borde);
            border-radius: 6px;
            padding: 1rem;
            text-decoration: none;
            color: var(--color-texto);
            text-align: left;
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }

        a.tarjeta-libro:hover,
        a.tarjeta-libro:focus-visible {
            border-color: var(--color-primario);
            box-shadow: 0 2px 8px rgba(31, 78, 121, 0.2);
        }

        a.tarjeta-libro img.portada {
            width: 100%;
            height: 180px;
            object-fit: contain;
            background-color: var(--color-fondo);
            border-radius: 4px;
            margin-bottom: 0.75rem;
        }

        a.tarjeta-libro h3 {
            margin: 0 0 0.25rem;
            font-size: 1.05rem;
            color: var(--color-primario);
        }

        a.tarjeta-libro p {
            margin: 0 0 0.15rem;
            font-size: 0.9rem;
        }

        a.tarjeta-libro p.categoria {
            margin-top: auto;
            padding-top: 0.5rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        nav.paginacion ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            justify-content: center;
            margin: 1.5rem 0 0;
            padding: 0;
        }

        nav.paginacion a,
        nav.paginacion span {
            display: inline-block;
            padding: 0.3rem 0.7rem;
            border: 1px solid var(--color-borde);
            border-radius: 4px;
            background-color: #ffffff;
            text-decoration: none;
            color: var(--color-primario);
            font-size: 0.9rem;
        }

        nav.paginacion a:hover {
            border-color: var(--color-primario);
        }

        nav.paginacion span.pagina-activa {
            background-color: var(--color-primario);
            border-color: var(--color-primario);
            color: #ffffff;
            font-weight: bold;
        }

        nav.paginacion span.pagina-inactiva,
        nav.paginacion span.pagina-omision {
            color: #6c757d;
        }

        .ficha img.portada {
            display: block;
            max-width: 240px;
            max-height: 320px;
            object-fit: contain;
            background-color: #ffffff;
            border: 1px solid var(--color-borde);
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        dl.ficha-campos {
            display: grid;
            grid-template-columns: minmax(12rem, auto) 1fr;
            gap: 0.4rem 1rem;
            background-color: #ffffff;
            border: 1px solid var(--color-borde);
            border-radius: 6px;
            padding: 1rem 1.25rem;
            margin: 0 0 1rem;
        }

        dl.ficha-campos dt {
            font-weight: bold;
            color: var(--color-primario);
        }

        dl.ficha-campos dd {
            margin: 0;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <h1>Biblioteca Universitaria</h1>

        <nav class="site-nav" aria-label="Navegación principal">
            <ul>
                <li>
                    <a href="{{ url('/') }}" @if (request()->is('/')) class="active" @endif>Inicio</a>
                </li>
                <li>
                    <a href="{{ route('catalogo.index') }}" @if (request()->routeIs('catalogo.*')) class="active" @endif>Catálogo</a>
                </li>
            </ul>
        </nav>
    </header>

    <main class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        Biblioteca Universitaria &mdash; Fase 1: entorno y base de datos
    </footer>
</body>
</html>
