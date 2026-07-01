<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pure resolution logic for a legal page: given the operator's per-locale content,
 * an optional override link, the fallback chain and the requested locale, it
 * decides what the consumer sees. No settings/HTTP I/O — that lives in the
 * LegalPages seam, so this stays trivially unit-testable and reusable by both the
 * privacy and (later) imprint pages.
 *
 * Precedence — override link > internal content > empty:
 *  1. A non-empty override URL wins outright (external link / redirect).
 *  2. Otherwise the first locale with content, trying the requested locale first
 *     and then each entry in the fallback chain.
 *  3. Otherwise empty (neutral placeholder — never fabricated legal text).
 */
final class LegalPageResolver
{
    /**
     * @param  array<string, string>  $content  locale => rich text HTML
     * @param  list<string>  $fallbackOrder  chain tried after the requested locale
     */
    public function resolve(?string $link, array $content, array $fallbackOrder, string $requested): LegalPage
    {
        $url = $this->normalizeLink($link);

        if ($url !== null) {
            return new LegalPage(externalUrl: $url, html: null);
        }

        return new LegalPage(externalUrl: null, html: $this->resolveContent($content, $fallbackOrder, $requested));
    }

    /**
     * First non-blank content along [requested, ...fallbackOrder] (de-duplicated),
     * or null when no locale in the chain has content.
     *
     * @param  array<string, string>  $content
     * @param  list<string>  $fallbackOrder
     */
    private function resolveContent(array $content, array $fallbackOrder, string $requested): ?string
    {
        /** @var list<string> $chain */
        $chain = array_values(array_unique([$requested, ...$fallbackOrder]));

        foreach ($chain as $locale) {
            $html = $content[$locale] ?? null;

            if (! $this->isBlank($html)) {
                return $html;
            }
        }

        return null;
    }

    /** Trims the override URL and treats an empty/whitespace value as "unset". */
    private function normalizeLink(?string $link): ?string
    {
        $link = $link === null ? '' : trim($link);

        return $link === '' ? null : $link;
    }

    /**
     * Rich text is "blank" when it has no visible content — an empty string or
     * markup with no text (e.g. TipTap's empty "<p></p>"). Guards against a
     * hollow editor value masquerading as real content.
     */
    private function isBlank(?string $html): bool
    {
        if ($html === null) {
            return true;
        }

        $text = trim(str_replace("\u{00A0}", ' ', strip_tags($html)));

        return $text === '';
    }
}
