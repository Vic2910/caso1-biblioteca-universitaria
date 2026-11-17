<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Inicio y cierre de sesión (008, D-037/D-039): un único formulario con
 * correo y contraseña, validado en el servidor, sin bloqueos ni esperas.
 */
class AuthController extends Controller
{
    /**
     * Muestra el formulario de acceso; quien ya tiene sesión va al catálogo.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('catalogo.index');
        }

        return view('auth.login');
    }

    /**
     * Autentica al usuario o devuelve el mensaje genérico en español.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credenciales = $request->validated();

        if (! Auth::attempt($credenciales)) {
            return redirect()
                ->route('login')
                ->withErrors(['credenciales' => __('auth.failed')])
                ->withInput();
        }

        $request->session()->regenerate();
        $request->session()->forget('acceso_inicial');

        return redirect()->intended(route('catalogo.index'));
    }

    /**
     * Cierra la sesión y descarta el token anterior.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('acceso_inicial', true);

        return redirect()->route('login');
    }
}
