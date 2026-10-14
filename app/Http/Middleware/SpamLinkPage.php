<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Frame for the signed spam-link pages: operator-facing, so rendered in the frozen
 * default locale, and never indexed or leaked through a referrer.
 */
final class SpamLinkPage
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Config::string('app.default_locale'));

        /** @var Response $response */
        $response = $next($request);

        return self::withHeaders($response);
    }

    public static function withHeaders(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
