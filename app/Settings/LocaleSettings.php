<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * DB-backed locales the consumer-facing withdrawal form offers, plus the default
 * applied when the consumer has not chosen one. Managed by the operator on the
 * Filament "Localization" settings page and seeded from the deployment's .env on
 * first migrate, so upgrades are behaviour-preserving.
 *
 * The settings page enforces the invariants relied on elsewhere: $available is
 * non-empty and always contains $default.
 */
final class LocaleSettings extends Settings
{
    /**
     * Locale codes offered in the consumer language switcher — a subset of the
     * shipped lang/* translations.
     *
     * @var list<string>
     */
    public array $available;

    /** Locale applied when the consumer has no valid locale cookie; ∈ $available. */
    public string $default;

    public static function group(): string
    {
        return 'locale';
    }
}
