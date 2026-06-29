<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for the locales the consumer-facing withdrawal form
 * offers. Backed by APP_AVAILABLE_LOCALES (config('app.available_locales')) and
 * falls back to the app default locale when unset. Both SetConsumerLocale and the
 * locale-set controller validate against this list, and the switcher renders it.
 */
final class ConsumerLocales
{
    /** @return list<string> */
    public static function available(): array
    {
        // config('app.available_locales') is always computed by config/app.php and
        // defaults to [APP_LOCALE], so the key is never missing — no fallback needed.
        /** @var list<string> $locales */
        $locales = array_values((array) config('app.available_locales'));

        return $locales;
    }

    /**
     * Returns the locale if it is a supported, available consumer locale, else null.
     * Accepts mixed so a raw cookie value (string|array|null) can be passed directly.
     */
    public static function resolve(mixed $locale): ?string
    {
        return is_string($locale) && in_array($locale, self::available(), strict: true)
            ? $locale
            : null;
    }
}
