<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Extensión de la sesión iniciada (009, D-053, contracts/session.md).
 * Solo renueva la actividad de la sesión: no escribe en base de datos.
 */
class SesionController extends Controller
{
    /**
     * Renueva la sesión conservando sus datos y vuelve a la pantalla anterior.
     */
    public function extender(Request $request): RedirectResponse
    {
        $request->session()->put('sesion_extendida_en', now()->timestamp);

        return redirect()->back()->with('success', 'Sesión extendida.');
    }
}
