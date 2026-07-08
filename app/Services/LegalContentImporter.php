<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\LegalSettings;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Applies a scraped Impressum / privacy-policy payload to the Phase-10
 * {@see LegalSettings} (spatie/laravel-settings) — the deterministic, tested core
 * of the legal-extraction skill. The (non-deterministic) scraping/extraction
 * happens in the skill, which feeds a validated JSON payload here.
 *
 * Guarantees:
 * - unknown keys or a malformed e-mail abort the import (nothing is written);
 * - populated fields are never clobbered unless `$overwrite` is set (the operator's
 *   reviewed legal text is protected);
 * - per-locale fields (address, addendum, privacy content) are scoped by `$locale`;
 *   structured imprint fields are global; `fallback_order` and the `*_link` overrides
 *   are never touched;
 * - rich-text (addendum, privacy) is sanitized with the same tool the render path uses
 *   ({@see Str::sanitizeHtml()}), and plain-text fields are tag-stripped — scraped HTML
 *   is untrusted.
 */
final class LegalContentImporter
{
    /** Payload imprint key => the global (locale-independent) LegalSettings property. */
    private const IMPRINT_GLOBAL_KEYS = [
        'name' => 'imprint_name',
        'legal_form' => 'imprint_legal_form',
        'represented_by' => 'imprint_represented_by',
        'email' => 'imprint_email',
        'phone' => 'imprint_phone',
        'contact_note' => 'imprint_contact_note',
        'register_court' => 'imprint_register_court',
        'register_number' => 'imprint_register_number',
        'vat_id' => 'imprint_vat_id',
        'business_id' => 'imprint_business_id',
        'supervisory_authority' => 'imprint_supervisory_authority',
        'chamber' => 'imprint_chamber',
        'job_title' => 'imprint_job_title',
        'professional_rules' => 'imprint_professional_rules',
        'liquidation_note' => 'imprint_liquidation_note',
    ];

    public function __construct(private readonly LegalSettings $settings) {}

    /**
     * @param  array<mixed>  $raw  The decoded JSON payload.
     *
     * @throws InvalidArgumentException on an invalid locale, unknown key or bad e-mail.
     */
    public function import(array $raw, string $locale, bool $overwrite): LegalImportResult
    {
        $this->assertValidLocale($locale);
        $parsed = $this->parse($raw);

        $conflicts = $this->conflicts($parsed, $locale);
        if ($conflicts !== [] && ! $overwrite) {
            return new LegalImportResult(applied: false, written: [], conflicts: $conflicts);
        }

        return new LegalImportResult(applied: true, written: $this->apply($parsed, $locale), conflicts: []);
    }

    /**
     * Validate and normalize the raw payload into the fields we may write.
     *
     * @param  array<mixed>  $raw
     * @return array{globals: array<string, string>, address: ?string, addendum: ?string, privacy: ?string}
     */
    private function parse(array $raw): array
    {
        foreach (array_keys($raw) as $key) {
            if (! in_array($key, ['imprint', 'privacy'], true)) {
                throw new InvalidArgumentException("Unknown top-level key '{$key}' (allowed: imprint, privacy).");
            }
        }

        $imprint = $this->section($raw, 'imprint');
        $privacy = $this->section($raw, 'privacy');

        $globals = [];
        $address = null;
        $addendum = null;
        foreach ($imprint as $key => $value) {
            if (isset(self::IMPRINT_GLOBAL_KEYS[$key])) {
                $globals[self::IMPRINT_GLOBAL_KEYS[$key]] = $this->plainText($this->asString($key, $value));
            } elseif ($key === 'address') {
                $address = $this->plainText($this->asString('address', $value));
            } elseif ($key === 'addendum') {
                $addendum = $this->sanitizeHtml($this->asString('addendum', $value));
            } else {
                throw new InvalidArgumentException("Unknown imprint key '{$key}'.");
            }
        }

        $privacyContent = null;
        foreach ($privacy as $key => $value) {
            if ($key === 'content') {
                $privacyContent = $this->sanitizeHtml($this->asString('content', $value));
            } else {
                throw new InvalidArgumentException("Unknown privacy key '{$key}'.");
            }
        }

        $email = $globals['imprint_email'] ?? '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("imprint.email is not a valid e-mail address: '{$email}'.");
        }

        // Skip empty values so a missing scrape field never blanks an existing setting.
        $globals = array_filter($globals, static fn (string $v): bool => $v !== '');

        return [
            'globals' => $globals,
            'address' => $address === '' ? null : $address,
            'addendum' => ($addendum === null || trim(strip_tags($addendum)) === '') ? null : $addendum,
            'privacy' => ($privacyContent === null || trim(strip_tags($privacyContent)) === '') ? null : $privacyContent,
        ];
    }

    /**
     * @param  array{globals: array<string, string>, address: ?string, addendum: ?string, privacy: ?string}  $parsed
     * @return list<string>
     */
    private function conflicts(array $parsed, string $locale): array
    {
        $conflicts = [];
        foreach ($parsed['globals'] as $property => $_value) {
            if ($this->isPopulated($this->settings->{$property})) {
                $conflicts[] = $property;
            }
        }
        if ($parsed['address'] !== null && ($this->settings->imprint_address[$locale] ?? '') !== '') {
            $conflicts[] = "imprint_address[{$locale}]";
        }
        if ($parsed['addendum'] !== null && ($this->settings->imprint_addendum[$locale] ?? '') !== '') {
            $conflicts[] = "imprint_addendum[{$locale}]";
        }
        if ($parsed['privacy'] !== null && ($this->settings->privacy_content[$locale] ?? '') !== '') {
            $conflicts[] = "privacy_content[{$locale}]";
        }

        return $conflicts;
    }

    /**
     * @param  array{globals: array<string, string>, address: ?string, addendum: ?string, privacy: ?string}  $parsed
     * @return list<string>
     */
    private function apply(array $parsed, string $locale): array
    {
        $written = [];
        foreach ($parsed['globals'] as $property => $value) {
            $this->settings->{$property} = $value;
            $written[] = $property;
        }
        if ($parsed['address'] !== null) {
            $this->settings->imprint_address = [$locale => $parsed['address']] + $this->settings->imprint_address;
            $written[] = "imprint_address[{$locale}]";
        }
        if ($parsed['addendum'] !== null) {
            $this->settings->imprint_addendum = [$locale => $parsed['addendum']] + $this->settings->imprint_addendum;
            $written[] = "imprint_addendum[{$locale}]";
        }
        if ($parsed['privacy'] !== null) {
            $this->settings->privacy_content = [$locale => $parsed['privacy']] + $this->settings->privacy_content;
            $written[] = "privacy_content[{$locale}]";
        }

        $this->settings->save();

        return $written;
    }

    /**
     * @param  array<mixed>  $raw
     * @return array<mixed>
     */
    private function section(array $raw, string $key): array
    {
        $value = $raw[$key] ?? [];
        if (! is_array($value)) {
            throw new InvalidArgumentException("'{$key}' must be a JSON object.");
        }

        return $value;
    }

    private function asString(string $key, mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException("Value for '{$key}' must be a string.");
    }

    private function plainText(string $value): string
    {
        return trim(strip_tags($value));
    }

    private function sanitizeHtml(string $value): string
    {
        return Str::sanitizeHtml($value);
    }

    private function isPopulated(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function assertValidLocale(string $locale): void
    {
        if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $locale) !== 1) {
            throw new InvalidArgumentException("Invalid --locale '{$locale}': expected a code like 'de' or 'de-at'.");
        }
    }
}
