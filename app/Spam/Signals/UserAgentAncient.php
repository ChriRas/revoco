<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;
use Illuminate\Support\Facades\Config;

/**
 * A missing or empty user agent, Internet Explorer, or a browser below the configured major floor.
 *
 * Reads the major version from the UA families Chrome (also Chromium Edge and Opera,
 * which carry `Chrome/`), Firefox and Safari (`Version/` with `Safari/`, without `Chrome/`). An
 * unrecognised family never fires, so unusual browsers are not flagged. The UA is
 * evaluated in memory only.
 */
final class UserAgentAncient implements SpamSignal
{
    public function key(): string
    {
        return 'user_agent_ancient';
    }

    public function fires(SubmissionContext $context): bool
    {
        $ua = trim((string) $context->userAgent);

        if ($ua === '' || str_contains($ua, 'MSIE ') || str_contains($ua, 'Trident/')) {
            return true;
        }

        if (preg_match('#Chrome/(\d+)#', $ua, $m) === 1) {
            return (int) $m[1] < Config::integer('revoco.spam.user_agent_floors.chrome');
        }

        if (preg_match('#Firefox/(\d+)#', $ua, $m) === 1) {
            return (int) $m[1] < Config::integer('revoco.spam.user_agent_floors.firefox');
        }

        if (str_contains($ua, 'Safari/') && preg_match('#Version/(\d+)#', $ua, $m) === 1) {
            return (int) $m[1] < Config::integer('revoco.spam.user_agent_floors.safari');
        }

        return false;
    }
}
