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
 * The privacy page is covered here; the imprint page reuses the same shape
 * (an imprint()/imprintUrl() pair) in its own slice.
 */
final class LegalPages
{
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
}
