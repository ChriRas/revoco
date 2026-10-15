<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReleaseChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Looks up the latest stable Revoco release for the panel's version badge. Scheduled daily;
 * also run by hand. Takes no input, so it is safe under --no-interaction. A deployment that
 * opted out (REVOCO_UPDATE_CHECK=false) makes no request. A failed check keeps the previous
 * result, logs a warning and exits non-zero.
 */
final class ReleaseCheckCommand extends Command
{
    protected $signature = 'revoco:release-check';

    protected $description = 'Look up the latest stable Revoco release for the panel version badge.';

    public function handle(ReleaseChecker $checker): int
    {
        if (! Config::boolean('revoco.update_check.enabled')) {
            $this->line('Update check is disabled (REVOCO_UPDATE_CHECK=false).');

            return self::SUCCESS;
        }

        $error = $checker->check();

        if ($error !== null) {
            Log::warning('Release check failed: '.$error);
            $this->error('Release check failed: '.$error);

            return self::FAILURE;
        }

        $this->line('Release check: ok');

        return self::SUCCESS;
    }
}
