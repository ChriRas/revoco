<?php

declare(strict_types=1);

namespace App\Support;

use App\Settings\LegalSettings;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Single completeness signal for operator-managed legal pages (slice-015).
 *
 * Composes the existing LegalPages seam: a page is complete when either its
 * structured content is configured OR the operator has set an external override
 * link (both carry full legal compliance). Drives the Filament panel red banner
 * and the public form setup notice; both clear automatically once configured.
 *
 * MissingSettings-safe: an unseeded or wiped settings store is treated as
 * INCOMPLETE (show all warnings) and never throws or returns a 500.
 */
final class LegalContent
{
    /**
     * Returns true when the imprint is considered complete: either the mandatory
     * § 5 DDG structured fields are configured (LegalPages::imprintIsConfigured)
     * or an external override link is set — the link satisfies the obligation.
     */
    public static function imprintComplete(): bool
    {
        try {
            $settings = app(LegalSettings::class);
            // Read inside the try so MissingSettings is caught here.
            $link = $settings->imprint_link;
        } catch (MissingSettings) {
            return false;
        }

        if (trim($link ?? '') !== '') {
            return true;
        }

        return LegalPages::imprintIsConfigured();
    }

    /**
     * Returns true when the privacy policy is considered complete: either at least
     * one locale has non-empty content (LegalPages::privacyIsConfigured) or an
     * external override link is set.
     */
    public static function privacyComplete(): bool
    {
        try {
            $settings = app(LegalSettings::class);
            // Read inside the try so MissingSettings is caught here.
            $link = $settings->privacy_link;
        } catch (MissingSettings) {
            return false;
        }

        if (trim($link ?? '') !== '') {
            return true;
        }

        return LegalPages::privacyIsConfigured();
    }

    /** Returns true when BOTH the imprint and privacy policy are complete. */
    public static function isComplete(): bool
    {
        return self::imprintComplete() && self::privacyComplete();
    }

    /**
     * Returns the page identifiers that are still incomplete. Empty list when
     * fully configured. Used to name missing pages in warning messages.
     *
     * @return list<string>
     */
    public static function missing(): array
    {
        $pages = [];

        if (! self::imprintComplete()) {
            $pages[] = 'imprint';
        }

        if (! self::privacyComplete()) {
            $pages[] = 'privacy';
        }

        return $pages;
    }
}
