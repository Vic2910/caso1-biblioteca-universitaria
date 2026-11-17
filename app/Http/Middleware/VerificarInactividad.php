<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caducidad por inactividad (008, D-038): `SESSION_LIFETIME` se aplica en el
 * servidor, no solo como expiración de la cookie, para que una petición con
 * una cookie antigua no procese ninguna operación.
 */
class VerificarInactividad
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sesion = $request->session();
        $limite = ((int) config('session.lifetime', 120)) * 60;
        $ultimaActividad = $sesion->get('ultima_actividad');

        if (is_numeric($ultimaActividad) && now()->getTimestamp() - (int) $ultimaActividad > $limite) {
            Auth::guard()->logout();

            $sesion->invalidate();
            $sesion->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', __('auth.expirada'));
        }

        $sesion->put('ultima_actividad', now()->getTimestamp());

        return $next($request);
    }
}
