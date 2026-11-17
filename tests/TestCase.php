<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    /**
     * 008 protege todas las rutas de negocio. Los escenarios heredados de
     * 001–007 se ejecutan con una sesión de prueba para no reescribirlos;
     * las suites que comprueban el acceso de invitados lo desactivan.
     */
    protected bool $sesionPorDefecto = true;

    /**
     * Cookie de sesión que el cliente de pruebas reenvía entre peticiones,
     * como haría un navegador. Laravel no la conserva automáticamente.
     */
    protected ?string $cookieSesion = null;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->sesionPorDefecto) {
            $usuario = User::factory()->create([
                'name' => 'Usuario de prueba',
            ]);

            Auth::guard()->login($usuario);
            $this->cookieSesion = $this->app['session.store']->getId();
        }
    }

    /**
     * Envía la cookie de sesión activa y fuerza la rehidratación del guard,
     * de modo que caducidades y cierres de sesión se comprueban igual que en
     * una aplicación real (cada petición resuelve al usuario desde la sesión).
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $sesion = $this->app['session.store'];

        if ($sesion->isStarted() && $this->cookieSesion === null) {
            $this->cookieSesion = $sesion->getId();
        }

        if ($this->cookieSesion !== null) {
            $cookies[(string) config('session.cookie')] = $this->cookieSesion;
        }

        Auth::forgetGuards();

        $respuesta = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        foreach ($respuesta->baseResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                $this->cookieSesion = $cookie->getValue() === '' ? null : $cookie->getValue();
            }
        }

        return $respuesta;
    }

    /**
     * Registra al usuario en la sesión, además de en el guard, para que la
     * rehidratación entre peticiones de las pruebas conserve la autenticación.
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        Auth::guard($guard)->login($user);
        $this->cookieSesion = $this->app['session.store']->getId();

        return $this;
    }

    /**
     * Deja la petición siguiente sin autenticar, como un visitante anónimo.
     */
    protected function sinSesion(): static
    {
        Auth::guard()->logout();
        Auth::forgetGuards();
        $this->app['session.store']->flush();
        $this->cookieSesion = null;

        return $this;
    }
}
