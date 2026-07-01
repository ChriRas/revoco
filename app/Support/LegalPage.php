<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolved outcome for a legal page in a given locale. Exactly one of three
 * states, in precedence order: an external override URL, internal rich-text
 * content (raw HTML for the winning locale), or empty (neither configured).
 *
 * The raw HTML is intentionally NOT rendered here — the consumer controller
 * safe-renders it via Filament's RichContentRenderer so unsafe markup is
 * stripped. This value object stays a pure carrier the imprint page reuses.
 */
final readonly class LegalPage
{
    public function __construct(
        public ?string $externalUrl,
        public ?string $html,
    ) {}

    /** An override URL is set — link/redirect externally instead of rendering. */
    public function isExternal(): bool
    {
        return $this->externalUrl !== null;
    }

    /** Neither an override URL nor internal content — show the neutral placeholder. */
    public function isEmpty(): bool
    {
        return $this->externalUrl === null && $this->html === null;
    }
}
