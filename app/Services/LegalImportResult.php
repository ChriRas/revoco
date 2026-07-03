<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Result of {@see LegalContentImporter::import()}. Immutable value object.
 *
 * When `applied` is false the import was blocked by the overwrite guard: `conflicts`
 * names the already-populated fields, and nothing was written.
 */
final readonly class LegalImportResult
{
    /**
     * @param  list<string>  $written  Settings fields written this import.
     * @param  list<string>  $conflicts  Populated fields that blocked a non-`--overwrite` import.
     */
    public function __construct(
        public bool $applied,
        public array $written,
        public array $conflicts,
    ) {}
}
