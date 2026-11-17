<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error de conexión — Biblioteca Universitaria</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="pagina-error">
    <main class="site-main">
        <div class="aviso" role="alert">
            <h1>Biblioteca Universitaria</h1>
            <h2>No se pudo conectar con la base de datos</h2>
            <p>
                La aplicación no se pudo conectar con la base de datos; para
                continuar, revise la configuración de conexión en el archivo
                <code>.env</code> (host, puerto, nombre de base de datos y
                credenciales) y vuelva a intentarlo.
            </p>
            <p>
                <a class="boton-primario" href="{{ url()->current() }}">Reintentar</a>
            </p>
        </div>
    </main>
</body>
</html>
