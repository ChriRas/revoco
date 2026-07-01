<?php

declare(strict_types=1);

namespace App\Support;

use App\Settings\LocaleSettings;
use Illuminate\Support\Facades\File;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Single source of truth for the locales the consumer-facing withdrawal form
 * offers. Backed by the DB-persisted App\Settings\LocaleSettings (operator-managed
 * on the Filament "Localization" page), seeded from the deployment's .env. Both
 * SetConsumerLocale and the locale-set controller validate against this list, and
 * the switcher renders it — they all resolve through this class, so it is the only
 * seam that had to move from config to settings.
 */
final class ConsumerLocales
{
    /**
     * Locales offered in the consumer switcher. Reads the operator-managed
     * LocaleSettings, but falls back to the framework base locale when that row
     * is missing/unseeded — § 356a: the consumer form (both GET / and the submit
     * POST run SetConsumerLocale) must never 500 on a misconfigured settings
     * store; a misconfigured-but-live shop stays submittable.
     *
     * @return list<string>
     */
    public static function available(): array
    {
        try {
            $available = app(LocaleSettings::class)->available;
        } catch (MissingSettings) {
            return [self::baseLocale()];
        }

        return $available !== [] ? $available : [self::baseLocale()];
    }

    /**
     * Default consumer locale (the operator-configured fallback when no valid
     * locale cookie is present). Always one of self::available(); falls back to
     * the framework base locale if LocaleSettings is missing (see available()).
     */
    public static function default(): string
    {
        try {
            return app(LocaleSettings::class)->default;
        } catch (MissingSettings) {
            return self::baseLocale();
        }
    }

    /** Laravel's configured base locale (APP_LOCALE) — the § 356a fallback. */
    private static function baseLocale(): string
    {
        $locale = config('app.locale');

        return is_string($locale) ? $locale : 'de';
    }

    /**
     * Locale codes the application ships consumer translations for — one per
     * directory under lang/. The operator can only offer a subset of these.
     *
     * @return list<string>
     */
    public static function shipped(): array
    {
        /** @var list<string> $codes */
        $codes = [];

        foreach (File::directories(lang_path()) as $dir) {
            if (is_string($dir)) {
                $codes[] = basename($dir);
            }
        }

        sort($codes);

        return $codes;
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
