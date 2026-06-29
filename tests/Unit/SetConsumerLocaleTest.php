<?php

declare(strict_types=1);

use App\Http\Middleware\SetConsumerLocale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

// Bind to Laravel's TestCase so config() and the container are available.
uses(TestCase::class);

// ---------------------------------------------------------------------------
// SetConsumerLocale middleware — unit tests for the cookie / allow-list logic.
//
// The middleware reads the raw request cookie (decrypted upstream by the web
// group's EncryptCookies in real requests) and only overrides the app locale
// when the value is a shipped, available locale. An unknown or absent cookie
// leaves the app default locale untouched.
// ---------------------------------------------------------------------------

/**
 * Run the middleware with the given locale cookie (null = no cookie) and return
 * the locale that was active when $next ran. Baseline app locale is 'de'.
 *
 * @param  array<string, string>  $cookies
 */
function runConsumerLocaleMiddleware(array $cookies): string
{
    config(['app.locale' => 'de', 'app.available_locales' => ['de', 'en']]);
    app()->setLocale('de');

    $middleware = new SetConsumerLocale;
    $request = Request::create('/', 'GET', [], $cookies);

    $captured = null;
    $next = function (Request $req) use (&$captured): Response {
        $captured = app()->getLocale();

        return new Response('ok');
    };

    $returned = $middleware->handle($request, $next);
    expect($returned->getContent())->toBe('ok');

    /** @var string $captured */
    return $captured;
}

it('applies a valid en locale cookie', function (): void {
    expect(runConsumerLocaleMiddleware([SetConsumerLocale::COOKIE_NAME => 'en']))->toBe('en');
});

it('applies a valid de locale cookie', function (): void {
    expect(runConsumerLocaleMiddleware([SetConsumerLocale::COOKIE_NAME => 'de']))->toBe('de');
});

it('ignores an unsupported locale cookie and keeps the app default', function (): void {
    expect(runConsumerLocaleMiddleware([SetConsumerLocale::COOKIE_NAME => 'fr']))->toBe('de');
});

it('keeps the app default when no locale cookie is present', function (): void {
    expect(runConsumerLocaleMiddleware([]))->toBe('de');
});

it('returns the $next response untouched', function (): void {
    config(['app.available_locales' => ['de', 'en']]);

    $middleware = new SetConsumerLocale;
    $request = Request::create('/', 'GET', [], [SetConsumerLocale::COOKIE_NAME => 'en']);

    $sentinel = new Response('sentinel-body', 201);
    $next = fn (Request $req): Response => $sentinel;

    expect($middleware->handle($request, $next))->toBe($sentinel);
});
