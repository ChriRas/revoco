<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Backend Locale
    |--------------------------------------------------------------------------
    |
    | Language for the operator panel UI only. Independent of APP_LOCALE, which
    | drives the consumer-facing withdrawal form. Supported: 'en', 'de'.
    | Falls back to 'en' for any unsupported value (avoids leaking via
    | APP_FALLBACK_LOCALE=de into the English backend).
    |
    */

    'locale' => env('BACKEND_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Supported Backend Locales
    |--------------------------------------------------------------------------
    |
    | The middleware validates BACKEND_LOCALE against this list and falls back
    | to 'en' for any unlisted value.
    |
    */

    'supported_locales' => ['en', 'de'],
];
