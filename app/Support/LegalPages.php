<?php

declare(strict_types=1);

namespace App\Support;

use App\Settings\LegalSettings;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Single seam between the operator-managed App\Settings\LegalSettings and the
 * consumer-facing legal pages / footer. Reads the DB-backed settings and delegates
 * the precedence + fallback decision to App\Support\LegalPageResolver.
 *
 * Falls back to an empty page when LegalSettings is missing/unseeded — § 356a: the
 * consumer form footer links to this seam, and the whole consumer flow runs under
 * SetConsumerLocale, so a misconfigured-but-live shop must never 500; an unseeded
 * store simply renders the neutral placeholder.
 *
 * Covers privacy policy (slice-013) and imprint § 5 DDG (slice-014).
 */
final class LegalPages
{
    // -----------------------------------------------------------------------
    // Privacy policy
    // -----------------------------------------------------------------------

    /** Resolved privacy page for $locale (defaults to the active app locale). */
    public static function privacy(?string $locale = null): LegalPage
    {
        $locale ??= app()->getLocale();

        try {
            $settings = app(LegalSettings::class);
            $link = $settings->privacy_link;
            $content = $settings->privacy_content;
            $order = $settings->fallback_order;
        } catch (MissingSettings) {
            return new LegalPage(externalUrl: null, html: null);
        }

        return (new LegalPageResolver)->resolve($link, $content, $order, $locale);
    }

    /**
     * Footer href for the privacy policy: the operator's override URL when set,
     * otherwise the internal legal.privacy route.
     */
    public static function privacyUrl(?string $locale = null): string
    {
        return self::privacy($locale)->externalUrl ?? route('legal.privacy');
    }

    // -----------------------------------------------------------------------
    // Imprint (§ 5 DDG)
    // -----------------------------------------------------------------------

    /**
     * Resolved imprint page for $locale (defaults to the active app locale).
     *
     * Applies the same override > internal > empty precedence as privacy:
     * - externalUrl is set when imprint_link is configured → route 302-redirects.
     * - html is the resolved addendum (per-locale rich text via fallback chain).
     *   The structured § 5 fields are read separately by the controller; most are
     *   locale-independent, except imprint_address which is per-locale (resolved
     *   there via the same fallback chain). None are packed into html.
     */
    public static function imprint(?string $locale = null): LegalPage
    {
        $locale ??= app()->getLocale();

        try {
            $settings = app(LegalSettings::class);
            $link = $settings->imprint_link;
            $addendum = $settings->imprint_addendum;
            $order = $settings->fallback_order;
        } catch (MissingSettings) {
            return new LegalPage(externalUrl: null, html: null);
        }

        return (new LegalPageResolver)->resolve($link, $addendum, $order, $locale);
    }

    /**
     * Footer href for the imprint: the operator's override URL when set,
     * otherwise the internal legal.imprint route.
     */
    public static function imprintUrl(?string $locale = null): string
    {
        return self::imprint($locale)->externalUrl ?? route('legal.imprint');
    }

    /**
     * Returns true when at least one locale has non-empty privacy-policy content.
     * Composed by App\Support\LegalContent (slice-015) — do not call directly from
     * views; use LegalContent::privacyComplete() which also accepts the link override.
     */
    public static function privacyIsConfigured(): bool
    {
        try {
            $settings = app(LegalSettings::class);
            $content = $settings->privacy_content;
        } catch (MissingSettings) {
            return false;
        }

        foreach ($content as $html) {
            if (trim($html) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true when the mandatory § 5 DDG core fields are non-empty:
     * name, address (for the deployment DEFAULT locale), and email.
     * Used by the S4 missing-content-warning slice.
     *
     * Address is per-locale; the default locale is the always-required baseline
     * (ConsumerLocales::default() — falls back gracefully when LocaleSettings
     * is unseeded). legal_form and represented_by depend on the entity type
     * (not universally required) and are excluded from the completion signal.
     */
    public static function imprintIsConfigured(): bool
    {
        try {
            $settings = app(LegalSettings::class);
            // Properties are read inside the try block so MissingSettings is caught.
            $name = $settings->imprint_name;
            $addressMap = $settings->imprint_address;
            $email = $settings->imprint_email;
        } catch (MissingSettings) {
            return false;
        }

        $defaultLocale = ConsumerLocales::default();

        return trim($name ?? '') !== ''
            && trim($addressMap[$defaultLocale] ?? '') !== ''
            && trim($email ?? '') !== '';
    }
}
