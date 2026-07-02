<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetConsumerLocale;
use App\Support\ConsumerLocales;
use Illuminate\Http\RedirectResponse;

/**
 * Sets the consumer form locale via a long-lived cookie, then redirects back to
 * the page the switcher was clicked from.
 *
 * The requested locale is validated against the operator-configured available
 * locales (App\Support\ConsumerLocales); an unknown value is ignored (no cookie
 * written), so only offered languages can ever be selected. SetConsumerLocale
 * reads this cookie on subsequent consumer requests.
 */
final class SetConsumerLocaleController extends Controller
{
    /** Cookie lifetime: one year, in minutes. */
    private const int COOKIE_MINUTES = 60 * 24 * 365;

    public function __invoke(string $locale): RedirectResponse
    {
        // Redirect back to the page the switcher was clicked from, but only when
        // it is same-origin — url()->previous() trusts the Referer header, so an
        // unconstrained back() would be an open redirect on this public GET route.
        $previous = url()->previous();
        $target = parse_url($previous, PHP_URL_HOST) === request()->getHost()
            ? $previous
            : route('withdrawal.form');

        $redirect = redirect()->to($target);

        if (ConsumerLocales::resolve($locale) !== null) {
            $redirect->withCookie(cookie(SetConsumerLocale::COOKIE_NAME, $locale, self::COOKIE_MINUTES));
        }

        return $redirect;
    }
}
