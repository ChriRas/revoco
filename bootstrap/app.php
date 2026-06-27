<?php

use App\Http\Middleware\SetConsumerLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The consumer locale preference is non-sensitive (a public language
        // choice). Leaving its cookie unencrypted keeps it readable and lets the
        // switcher round-trip it cleanly; SetConsumerLocale still validates the
        // value against the available-locales allow-list before applying it.
        $middleware->encryptCookies(except: [
            SetConsumerLocale::COOKIE_NAME,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
