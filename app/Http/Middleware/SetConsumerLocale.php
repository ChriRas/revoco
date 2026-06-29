<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ConsumerLocales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale for the consumer-facing withdrawal form from the
 * locale cookie the language switcher writes.
 *
 * APP_LOCALE is the default; this middleware lets the consumer override it for
 * the duration of their session via the switcher. The chosen value is validated
 * against APP_AVAILABLE_LOCALES (config('app.available_locales')) — an unknown or
 * absent cookie leaves the app default locale untouched, so a stale or forged
 * cookie can never select an unshipped language.
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
        $locale = ConsumerLocales::resolve($request->cookie(self::COOKIE_NAME));

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
