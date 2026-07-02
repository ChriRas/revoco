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
 * Covers both the privacy policy (slice-013) and the § 5 DDG imprint
 * (slice-014). All fields default empty — a fresh install has neither content
 * nor override links; the consumer pages render neutral "not configured yet"
 * placeholders (never fabricated legal text).
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

    // -----------------------------------------------------------------------
    // § 5 DDG imprint fields (slice-014)
    // Operator data is locale-independent; labels are i18n keys.
    // Exception: imprint_address is per-locale (country name, ß→ss etc.);
    // all other structured fields are plain ?string.
    // The free-form addendum is also per language (resolved via the fallback chain).
    // -----------------------------------------------------------------------

    /** Trading name or legal name of the entity (§ 5 Abs. 1 Nr. 1). */
    public ?string $imprint_name = null;

    /** Legal form, e.g. "GmbH", "e.K." (required for legal entities). */
    public ?string $imprint_legal_form = null;

    /** Name of the authorized representative(s) (required for legal entities). */
    public ?string $imprint_represented_by = null;

    /**
     * Full postal address per consumer locale (no P.O. box — § 5 Nr. 1).
     * Keyed by locale code, e.g. ['de' => 'Musterstraße 1 …', 'en' => 'Musterstrasse 1 …'].
     * Resolved via the same fallback chain as the addendum ($fallback_order).
     * The deployment DEFAULT locale must be populated for the imprint to be considered
     * configured (see App\Support\LegalPages::imprintIsConfigured()).
     *
     * @var array<string, string>
     */
    public array $imprint_address = [];

    /** E-mail address for fast contact (§ 5 Abs. 1 Nr. 2 / post-EuGH C-298/07). */
    public ?string $imprint_email = null;

    /** Phone number or other fast second contact channel (recommended, post-EuGH). */
    public ?string $imprint_phone = null;

    /** Free note for additional contact means (e.g. contact-form notice). */
    public ?string $imprint_contact_note = null;

    /** Name of the commercial/other register (§ 5 Abs. 1 Nr. 4), if applicable. */
    public ?string $imprint_register_court = null;

    /** Register number (§ 5 Abs. 1 Nr. 4), if applicable. */
    public ?string $imprint_register_number = null;

    /** VAT identification number (§ 27a UStG) — § 5 Abs. 1 Nr. 6, if held. */
    public ?string $imprint_vat_id = null;

    /** Economic identification number (§ 139c AO) — § 5 Abs. 1 Nr. 6, if held. */
    public ?string $imprint_business_id = null;

    /** Supervisory authority — only for activities requiring official authorization (§ 5 Nr. 3). */
    public ?string $imprint_supervisory_authority = null;

    /** Professional chamber — regulated professions only (§ 5 Nr. 5). */
    public ?string $imprint_chamber = null;

    /** Statutory job title and country granting it — regulated professions only (§ 5 Nr. 5). */
    public ?string $imprint_job_title = null;

    /** Professional rules and how to access them — regulated professions only (§ 5 Nr. 5). */
    public ?string $imprint_professional_rules = null;

    /** Liquidation / winding-up statement — companies in dissolution only (§ 5 Nr. 7). */
    public ?string $imprint_liquidation_note = null;

    /**
     * Per-language free-form addendum (rich text HTML), e.g. ['de' => '<p>…</p>'].
     * Resolved via the same fallback chain as the privacy policy. Intended for
     * operator-specific additions that do not fit the structured fields.
     *
     * @var array<string, string>
     */
    public array $imprint_addendum = [];

    /**
     * External imprint URL override. When set the footer links here and the
     * /impressum route 302-redirects there instead of rendering the internal page.
     */
    public ?string $imprint_link = null;

    public static function group(): string
    {
        return 'legal';
    }
}
