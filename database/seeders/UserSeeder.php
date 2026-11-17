<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cuenta local creada por el equipo para el inicio de sesión (008, D-037).
 * La contraseña nunca se escribe en claro en el repositorio: se toma del
 * entorno y se almacena únicamente como hash.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('seeding.usuario_password');

        if ($password === '') {
            $password = Str::password(20);

            $this->command?->warn('USUARIO_SEMILLA_PASSWORD no está definida; se ha generado una contraseña aleatoria.');
            $this->command?->info("Correo: biblioteca@ejemplo.local | Contraseña: {$password}");
        }

        User::updateOrCreate(
            ['email' => 'biblioteca@ejemplo.local'],
            [
                'name' => 'Biblioteca Universitaria',
                'password' => Hash::make($password),
            ],
        );
    }
}
