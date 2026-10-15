<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Answers "which version is running" and "is a newer stable release out" from config and the
 * cache only — safe to call while rendering a page, it never touches the network.
 */
final class ReleaseInfo
{
    public function runningVersion(): string
    {
        return Config::string('revoco.version');
    }

    /**
     * The cached stable release when it is newer than the running version, else null: when
     * the check is opted out, nothing is cached, the running version is not SemVer (`dev`),
     * or the cached release is not newer.
     *
     * @return array{version: string, tag: string, url: string}|null
     */
    public function newerRelease(): ?array
    {
        if (! Config::boolean('revoco.update_check.enabled')) {
            return null;
        }

        $running = $this->runningVersion();

        if (! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', $running)) {
            return null;
        }

        $cached = Cache::get(ReleaseChecker::CACHE_KEY);

        if (! is_array($cached) || ! is_string($cached['version'] ?? null) || ! is_string($cached['tag'] ?? null)) {
            return null;
        }

        if (! preg_match('/^\d+\.\d+\.\d+$/', $cached['version']) || ! version_compare($cached['version'], $running, '>')) {
            return null;
        }

        return [
            'version' => $cached['version'],
            'tag' => $cached['tag'],
            'url' => 'https://github.com/'.Config::string('revoco.update_check.repository').'/releases/tag/'.rawurlencode($cached['tag']),
        ];
    }
}
