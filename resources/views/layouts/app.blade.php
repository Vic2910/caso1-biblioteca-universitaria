<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Biblioteca Universitaria')</title>
    <style>
        :root {
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
        }

        header.site-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: normal;
            letter-spacing: 0.04em;
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
    </style>
</head>
<body>
    <header class="site-header">
        <h1>Biblioteca Universitaria</h1>
    </header>

    <main class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        Biblioteca Universitaria &mdash; Fase 1: entorno y base de datos
    </footer>
</body>
</html>
