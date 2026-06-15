<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Active Theme
    |--------------------------------------------------------------------------
    |
    | Switches the `--wf-*` CSS token set via `data-theme` on the form card.
    | The public repo ships the neutral theme plus the mechanism only; concrete
    | brand overlays (logos, colours, fonts) live in the private infra repo and
    | are selected per deployment by setting APP_THEME to the overlay's name.
    |
    */

    'theme' => env('APP_THEME', 'neutral'),

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Neutral placeholders. Operators override these per deployment via .env
    | (see .env.example). No real brand assets ship in this public repo.
    |
    */

    'brand_name' => env('REVOCO_BRAND_NAME'),

    'logo_url' => env('REVOCO_LOGO_URL'),

    /*
    |--------------------------------------------------------------------------
    | Legal Footer Links
    |--------------------------------------------------------------------------
    |
    | Imprint / privacy policy URLs. Default to a neutral '#' placeholder so the
    | links render structurally; operators point them at their own pages.
    |
    */

    'imprint_url' => env('REVOCO_IMPRINT_URL', '#'),

    'privacy_url' => env('REVOCO_PRIVACY_URL', '#'),

    /*
    |--------------------------------------------------------------------------
    | Delivery — merchant notification + optional ntfy push
    |--------------------------------------------------------------------------
    |
    | Mail transport itself is configured via the standard MAIL_* env (config/mail).
    | The merchant notification goes to MERCHANT_NOTIFICATION_EMAIL (when set).
    | ntfy push is opt-in (NTFY_ENABLED) and data-minimal — see app/Services/Ntfy.
    |
    */

    'merchant_email' => env('MERCHANT_NOTIFICATION_EMAIL'),

    'ntfy' => [
        'enabled' => (bool) env('NTFY_ENABLED', false),
        'server' => env('NTFY_SERVER', 'https://ntfy.sh'),
        'topic' => env('NTFY_TOPIC', ''),
        'token' => env('NTFY_TOKEN'),
    ],

];
