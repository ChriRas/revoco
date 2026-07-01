<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * DB-backed legal-page content the operator maintains on the Filament "Legal"
 * settings page. Revoco never authors substantive legal text (see
 * .claude/project/design/legal-compliance.md): the app ships only the mechanism,
 * the operator supplies the content and bears the controller liability.
 *
 * This slice covers the privacy policy; the imprint reuses the same pattern and
 * lands in its own slice. All fields default empty — a fresh install has neither
 * content nor an override link, which the consumer page renders as a neutral
 * "not configured yet" placeholder (never fabricated legal text).
 */
final class LegalSettings extends Settings
{
    /**
     * Privacy-policy rich text (HTML) keyed by locale code, e.g. ['de' => '<p>…</p>'].
     * A locale absent here falls back along $fallback_order.
     *
     * @var array<string, string>
     */
    public array $privacy_content;

    /**
     * External privacy-policy URL. When set it takes precedence over the internal
     * content: the footer links here and the internal route 302-redirects here.
     */
    public ?string $privacy_link;

    /**
     * Ordered locale chain tried when the requested locale has no content. The
     * requested locale is always tried first; this is the fallback after it.
     * Seeded with the deployment's default locale.
     *
     * @var list<string>
     */
    public array $fallback_order;

    public static function group(): string
    {
        return 'legal';
    }
}
