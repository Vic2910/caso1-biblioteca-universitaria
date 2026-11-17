<?php

declare(strict_types=1);

return [
    /*
     | Contraseña de la cuenta local creada por el equipo (008, D-037).
     | Se lee del entorno para no versionar credenciales; si no se define,
     | el seeder genera una contraseña aleatoria y la muestra por consola.
     */
    'usuario_password' => env('USUARIO_SEMILLA_PASSWORD'),
];
