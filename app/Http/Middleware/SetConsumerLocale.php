<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ConsumerLocales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale for the consumer-facing withdrawal form.
 *
 * With no valid cookie present, the operator-configured default applies
 * (ConsumerLocales::default(), backed by App\Settings\LocaleSettings) — this is
 * how a panel-chosen default that diverges from APP_LOCALE takes effect for a
 * fresh visitor. The language switcher then lets the consumer override it for the
 * duration of their session via a cookie; that value is validated against the
 * operator-configured available locales, so a stale or forged cookie can never
 * select an unavailable language and falls back to the configured default.
 *
 * Everything resolves through App\Support\ConsumerLocales (the single settings
 * seam), so the DB-backed default/allow-list is the only source of truth here.
 *
 * Scoped to the consumer routes only (see routes/web.php); the operator panel
 * has its own SetBackendLocale.
 */
final class SetConsumerLocale
{
    /** Cookie carrying the consumer's chosen form locale. */
    public const string COOKIE_NAME = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = ConsumerLocales::resolve($request->cookie(self::COOKIE_NAME))
            ?? ConsumerLocales::default();

        app()->setLocale($locale);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
