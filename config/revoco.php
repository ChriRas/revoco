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
    | AGPL-3.0 §13: a user interacting over the network must be offered the running
    | source. Defaults to the upstream repository; an operator who MODIFIES the code
    | must point this at their own corresponding source (their fork) to stay compliant.
    */

    'source_url' => env('REVOCO_SOURCE_URL', 'https://github.com/ChriRas/revoco'),

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

    /*
    |--------------------------------------------------------------------------
    | Spam — non-blocking score engine
    |--------------------------------------------------------------------------
    |
    | Every submit is scored by the signals in app/Spam/Signals; the score only
    | classifies (green / yellow / red), it never blocks (§ 356a). Code defaults
    | only: tuning is reviewed in code, there is no env switch or panel for it.
    |
    | weights:              points per signal key; an unknown key weighs 0.
    | thresholds:           score >= yellow is yellow, score >= red is red.
    | min_fill_seconds:     a faster submit than this after the form render is
    |                       "too fast".
    | user_agent_floors:    browser major versions below these count as ancient.
    |
    */

    'spam' => [
        'weights' => [
            'honeypot' => 100,
            'content_gibberish' => 45,
            'render_token_invalid' => 40,
            'fill_time_too_fast' => 40,
            'throttle' => 30,
            'field_lengths_equal' => 25,
            'user_agent_ancient' => 25,
            'name_without_space' => 15,
            // Network signals (slice-028): classify only, and their sum stays below the red threshold.
            'drop_network' => 30,
            'asn_drop' => 20,
            'learned_network' => 10,
            'tor_exit' => 5,
            'country_outside_shipping' => 4,
            // Abused-address memory (slice-029): at the yellow threshold, so a hit alone is yellow and never red.
            'abused_address' => 30,
        ],

        'thresholds' => [
            'yellow' => 30,
            'red' => 70,
        ],

        /*
         * Auto-sorting (slice-030): when `trigger` fired together with any of `with_any`,
         * the submit is stored with the verdict "spam" right away (source `auto`). Network
         * signals are deliberately absent — genuine customers abroad or behind VPNs.
         */
        'auto_confirm' => [
            'trigger' => 'honeypot',
            'with_any' => ['user_agent_ancient', 'fill_time_too_fast', 'render_token_invalid', 'content_gibberish'],
        ],

        'min_fill_seconds' => 3,

        'user_agent_floors' => [
            'chrome' => 100,
            'firefox' => 100,
            'safari' => 14,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Network — local country / ASN lookup and listed-network feeds
    |--------------------------------------------------------------------------
    |
    | `revoco:network-data:refresh` downloads the files below into `path`; the
    | submit only ever reads them locally. The raw IP is never persisted, only the
    | country and ASN derived from it.
    |
    | path:       directory of the downloaded databases and feed files.
    | geo:        DB-IP Lite (country + ASN); `{type}` is `country` or `asn`,
    |             `{month}` is YYYY-MM. The current month 404s until it is published,
    |             the refresh then falls back to the previous month.
    | feeds:      listed-network feeds (IP blocks, listed ASNs, Tor exit nodes).
    | timeout:    HTTP timeout in seconds per download.
    | learning:   a network (ASN) is "learned" once `min_hits` confirmed-spam rows
    |             of it fall within `window_days`.
    |
    */

    'network' => [
        'path' => storage_path('app/private/network'),

        'geo' => [
            'url' => 'https://download.db-ip.com/free/dbip-{type}-lite-{month}.mmdb.gz',
        ],

        'feeds' => [
            'drop_v4' => 'https://www.spamhaus.org/drop/drop_v4.json',
            'drop_v6' => 'https://www.spamhaus.org/drop/drop_v6.json',
            'asn_drop' => 'https://www.spamhaus.org/drop/asndrop.json',
            'tor' => 'https://check.torproject.org/torbulkexitlist',
        ],

        'timeout' => 30,

        'learning' => [
            'min_hits' => 3,
            'window_days' => 90,
        ],
    ],

];
