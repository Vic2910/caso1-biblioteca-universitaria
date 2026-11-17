<?php

use App\Exceptions\FalloConexion;
use App\Http\Middleware\VerificarInactividad;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [VerificarInactividad::class]);

        /*
         * FR-009 / D-038: toda petición sin sesión vuelve al formulario de
         * acceso. La presencia de la cookie distingue el acceso inicial de
         * una sesión caducada tras dos horas de inactividad.
         */
        $middleware->redirectGuestsTo(function (Request $request): string {
            $accesoInicial = ! $request->hasCookie((string) config('session.cookie'))
                || $request->session()->has('acceso_inicial');

            $mensaje = $accesoInicial ? __('auth.inicie') : __('auth.expirada');

            if ($accesoInicial) {
                $request->session()->flash('acceso_inicial', true);
            }

            $request->session()->flash('error', $mensaje);

            return route('login');
        });
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

        /*
         * FR-005 / D-040: un token CSRF ausente, caducado o manipulado no
         * llega al controlador; vuelve al origen con el mensaje exacto y
         * conserva lo introducido en la sesión.
         */
        $exceptions->render(function (HttpException $excepcion, Request $request) {
            if ($excepcion->getStatusCode() !== 419) {
                return null;
            }

            return redirect()
                ->back()
                ->withErrors(['csrf' => __('auth.csrf')])
                ->withInput();
        });
    })->create();
