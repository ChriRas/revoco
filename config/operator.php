<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Operator Account Credentials
    |--------------------------------------------------------------------------
    |
    | Used by `php artisan app:operator` to provision the single admin account.
    | Set in .env; the password env var may be removed after the first run.
    |
    */

    'email' => env('OPERATOR_EMAIL', ''),
    'password' => env('OPERATOR_PASSWORD', ''),
];
