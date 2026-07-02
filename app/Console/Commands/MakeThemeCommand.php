<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ThemeOverlay;
use App\Services\ThemeOverlayGenerator;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\text;

/**
 * Generate a Revoco brand theme overlay (--wf-* / data-theme) from a shop's
 * corporate identity. This is the deterministic core of the design-adoption
 * skill: the skill scrapes a site and feeds the extracted tokens here; this
 * command validates them against the token contract, renders the overlay, and
 * prints a placement report.
 *
 * Parameter-driven — pass the slug + brand tokens as options, or let the command
 * prompt for the required slug on a TTY. Fails cleanly under --no-interaction.
 * Writes nothing into this repo: output goes to --output (a file) or stdout, for
 * the operator to place in their private infra repo / deployment mount.
 */
final class MakeThemeCommand extends Command
{
    protected $signature = 'revoco:make-theme
                            {--slug= : Theme name (data-theme value + APP_THEME); lowercase, hyphenated}
                            {--accent= : Accent colour (--wf-accent)}
                            {--accent-hover= : Accent hover colour (--wf-accent-hover)}
                            {--fg= : Body text colour (--wf-fg)}
                            {--heading-fg= : Heading colour (--wf-heading-fg)}
                            {--muted= : Muted text colour (--wf-muted)}
                            {--card-bg= : Card background (--wf-card-bg)}
                            {--card-border= : Card border colour (--wf-card-border)}
                            {--btn-bg= : Button background (--wf-btn-bg)}
                            {--btn-hover= : Button hover background (--wf-btn-hover)}
                            {--btn-fg= : Button text colour (--wf-btn-fg)}
                            {--font= : Body font stack (--wf-font)}
                            {--font-display= : Heading font stack (--wf-font-display)}
                            {--logo-url= : Logo URL (guidance: REVOCO_LOGO_URL)}
                            {--brand-name= : Brand name (guidance: REVOCO_BRAND_NAME)}
                            {--output= : Write the overlay CSS to this file (default: stdout)}';

    protected $description = 'Generate a Revoco brand theme overlay from a shop corporate identity.';

    /**
     * Option name => the --wf-* token it maps to. Iteration order fixes the
     * emitted-token order, so the rendered overlay is deterministic.
     */
    private const OPTION_TOKEN_MAP = [
        'accent' => '--wf-accent',
        'accent-hover' => '--wf-accent-hover',
        'fg' => '--wf-fg',
        'heading-fg' => '--wf-heading-fg',
        'muted' => '--wf-muted',
        'card-bg' => '--wf-card-bg',
        'card-border' => '--wf-card-border',
        'btn-bg' => '--wf-btn-bg',
        'btn-hover' => '--wf-btn-hover',
        'btn-fg' => '--wf-btn-fg',
        'font' => '--wf-font',
        'font-display' => '--wf-font-display',
    ];

    public function handle(ThemeOverlayGenerator $generator): int
    {
        $slug = $this->resolveSlug();
        if ($slug === null) {
            return self::FAILURE;
        }

        try {
            $overlay = $generator->generate(
                $slug,
                $this->collectTokens(),
                $this->stringOption('logo-url'),
                $this->stringOption('brand-name'),
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return $this->emit($overlay);
    }

    /** Resolve the required slug from the option or an interactive prompt. */
    private function resolveSlug(): ?string
    {
        $slug = $this->stringOption('slug');
        if ($slug !== null) {
            return $slug;
        }

        if (! $this->input->isInteractive()) {
            $this->error('The --slug option is required in non-interactive mode.');

            return null;
        }

        return text(label: 'Theme slug (data-theme / APP_THEME value)', required: true);
    }

    /**
     * @return array<string, string>
     */
    private function collectTokens(): array
    {
        $tokens = [];
        foreach (self::OPTION_TOKEN_MAP as $option => $token) {
            $value = $this->stringOption($option);
            if ($value !== null) {
                $tokens[$token] = $value;
            }
        }

        return $tokens;
    }

    private function emit(ThemeOverlay $overlay): int
    {
        foreach ($overlay->warnings as $warning) {
            $this->warn($warning);
        }

        $output = $this->stringOption('output');
        if ($output !== null) {
            if (file_put_contents($output, $overlay->css) === false) {
                $this->error("Could not write the overlay to {$output}.");

                return self::FAILURE;
            }
            $this->info("Overlay written to {$output}");
        } else {
            $this->line($overlay->css);
        }

        $this->line('');
        $this->info('Placement report:');
        foreach ($overlay->report as $line) {
            $this->line("  - {$line}");
        }

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
