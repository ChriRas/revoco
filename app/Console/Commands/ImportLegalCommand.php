<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LegalContentImporter;
use App\Settings\LegalSettings;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\text;

/**
 * Import a scraped Impressum / privacy-policy payload into the operator's legal
 * settings. Deterministic core of the legal-extraction skill: the skill scrapes the
 * operator's pages and writes a JSON payload; this command validates, sanitizes and
 * persists it into {@see LegalSettings}.
 *
 * Parameter-driven — pass `--locale` and `--input` (a JSON file), or let the command
 * prompt on a TTY. Fails cleanly under --no-interaction. Refuses to overwrite
 * already-populated legal fields unless `--overwrite` is given. The operator reviews
 * the result in Filament → Legal before relying on it.
 */
final class ImportLegalCommand extends Command
{
    protected $signature = 'revoco:import-legal
                            {--locale= : Locale the scraped pages are written in (e.g. de)}
                            {--input= : Path to the JSON payload file}
                            {--overwrite : Replace already-populated legal fields instead of refusing}';

    protected $description = 'Import scraped Impressum / privacy content into the legal settings.';

    public function handle(LegalContentImporter $importer): int
    {
        $locale = $this->resolveLocale();
        if ($locale === null) {
            return self::FAILURE;
        }

        $payload = $this->readPayload();
        if ($payload === null) {
            return self::FAILURE;
        }

        try {
            $result = $importer->import($payload, $locale, (bool) $this->option('overwrite'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result->applied) {
            $this->error('Refusing to overwrite already-populated legal fields: '.implode(', ', $result->conflicts));
            $this->line('Pass --overwrite to replace them, or clear them first in Filament → Legal.');

            return self::FAILURE;
        }

        return $this->report($result->written, $locale);
    }

    private function resolveLocale(): ?string
    {
        $locale = $this->stringOption('locale');
        if ($locale !== null) {
            return $locale;
        }

        if (! $this->input->isInteractive()) {
            $this->error('The --locale option is required in non-interactive mode.');

            return null;
        }

        return text(label: 'Locale the scraped pages are written in (e.g. de)', required: true);
    }

    /**
     * Resolve and decode the JSON payload file.
     *
     * @return array<mixed>|null
     */
    private function readPayload(): ?array
    {
        $path = $this->stringOption('input');
        if ($path === null) {
            if (! $this->input->isInteractive()) {
                $this->error('The --input option (path to the JSON payload) is required in non-interactive mode.');

                return null;
            }
            $path = text(label: 'Path to the JSON payload file', required: true);
        }

        if (! is_file($path)) {
            $this->error("Input file not found: {$path}");

            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            $this->error("Input file is not a valid JSON object: {$path}");

            return null;
        }

        return $decoded;
    }

    /**
     * @param  list<string>  $written
     */
    private function report(array $written, string $locale): int
    {
        if ($written === []) {
            $this->warn('Nothing to import — the payload carried no legal fields.');

            return self::SUCCESS;
        }

        $this->info(count($written)." legal field(s) imported for locale '{$locale}':");
        foreach ($written as $field) {
            $this->line("  - {$field}");
        }

        $this->line('');
        $this->info('Review the imported content in Filament → Legal before relying on it — you are the data controller; Revoco makes no legal-correctness guarantee.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
