<?php

use App\Exceptions\FalloConexion;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // FR-008 / research D-006: ante un fallo de conexión a la base de
        // datos la aplicación no continúa; muestra un aviso en español sin
        // credenciales ni SQL (contrato user-messages.md §1).
        $exceptions->render(function (Throwable $excepcion) {
            if (FalloConexion::detectar($excepcion)) {
                return response()->view('errors.conexion', [], 500);
            }

            return null;
        });
    })->create();
