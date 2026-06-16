<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale to the configured backend locale for the
 * duration of the operator panel request (including Livewire AJAX requests
 * when registered with isPersistent: true).
 *
 * BACKEND_LOCALE drives this; APP_LOCALE drives the consumer-facing form.
 * Falls back to 'en' for any unlisted locale to prevent APP_FALLBACK_LOCALE=de
 * from leaking German into the English panel.
 */
final class SetBackendLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('operator.supported_locales', ['en', 'de']);

        /** @var string $configured */
        $configured = config('operator.locale', 'en');

        $locale = in_array($configured, $supported, strict: true) ? $configured : 'en';

        app()->setLocale($locale);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
