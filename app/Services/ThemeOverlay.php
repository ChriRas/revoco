<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Result of {@see ThemeOverlayGenerator::generate()}: the CSS overlay block plus
 * the operator-facing placement report and diagnostics. Immutable value object.
 */
final readonly class ThemeOverlay
{
    /**
     * @param  string  $css  The `.wf-card[data-theme="…"]` overlay block.
     * @param  list<string>  $report  Operator-facing placement instructions.
     * @param  list<string>  $emittedTokens  The --wf-* token names written into the overlay.
     * @param  list<string>  $warnings  Non-fatal notes (e.g. no tokens provided).
     */
    public function __construct(
        public string $css,
        public array $report,
        public array $emittedTokens,
        public array $warnings,
    ) {}
}
