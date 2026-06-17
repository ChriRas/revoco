<?php

declare(strict_types=1);

use App\Http\Middleware\SetBackendLocale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

// Bind to Laravel's TestCase so config(), app(), and the service container
// are available. Unit tests are not covered by the Pest.php Feature binding.
uses(TestCase::class);

// ---------------------------------------------------------------------------
// SetBackendLocale middleware — unit tests for the guard / allow-list logic.
//
// These tests exercise the middleware directly (no Filament, no DB) and prove
// that the allow-list and fallback behave correctly independently of any HTTP
// routing or Livewire test-helper quirks.
// ---------------------------------------------------------------------------

/**
 * Helper: run the middleware for a given configured locale and return the
 * locale that was active when $next was called.
 */
function runMiddlewareWithLocale(string $configuredLocale): string
{
    config(['operator.locale' => $configuredLocale]);

    $middleware = new SetBackendLocale;
    $request = Request::create('/admin/withdrawals', 'GET');

    $capturedLocale = null;

    $next = function (Request $req) use (&$capturedLocale): Response {
        $capturedLocale = app()->getLocale();

        return new Response('ok');
    };

    $returned = $middleware->handle($request, $next);

    // The middleware must return the $next result untouched.
    expect($returned)->toBeInstanceOf(Response::class);
    expect($returned->getContent())->toBe('ok');

    /** @var string $capturedLocale */
    return $capturedLocale;
}

it('sets the locale to en when BACKEND_LOCALE is en', function (): void {
    expect(runMiddlewareWithLocale('en'))->toBe('en');
});

it('sets the locale to de when BACKEND_LOCALE is de', function (): void {
    expect(runMiddlewareWithLocale('de'))->toBe('de');
});

it('falls back to en for an unsupported locale (fr)', function (): void {
    // "fr" is not in the supported-locales allow-list; the middleware must
    // fall back to "en" rather than forwarding an unsupported value that could
    // trigger APP_FALLBACK_LOCALE=de to leak German into the English panel.
    expect(runMiddlewareWithLocale('fr'))->toBe('en');
});

it('returns the $next response untouched', function (): void {
    config(['operator.locale' => 'en']);

    $middleware = new SetBackendLocale;
    $request = Request::create('/admin', 'GET');

    $sentinel = new Response('sentinel-body', 201);
    $next = fn (Request $req): Response => $sentinel;

    $result = $middleware->handle($request, $next);

    expect($result)->toBe($sentinel);
});
