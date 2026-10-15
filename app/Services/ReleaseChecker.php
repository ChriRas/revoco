<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks the public GitHub Releases API for the latest stable release and caches the answer.
 * One anonymous GET, no instance data in it. Only a plain `vX.Y.Z` tag counts as stable; on
 * any failure the previous cached result stays in place. Runs from the scheduler only —
 * the panel reads the cache via ReleaseInfo and never makes a request.
 */
final class ReleaseChecker
{
    public const CACHE_KEY = 'revoco.release.latest';

    /**
     * Fetches and stores the latest stable release.
     *
     * @return string|null null on success, otherwise the reason the check failed
     */
    public function check(): ?string
    {
        $repository = Config::string('revoco.update_check.repository');

        try {
            $response = Http::timeout(Config::integer('revoco.update_check.timeout'))
                ->withUserAgent('Revoco')
                ->accept('application/vnd.github+json')
                ->get('https://api.github.com/repos/'.$repository.'/releases/latest');
        } catch (Throwable $e) {
            return 'request failed: '.$e->getMessage();
        }

        if (! $response->successful()) {
            return 'unexpected HTTP status '.$response->status();
        }

        $payload = $response->json();
        $tag = is_array($payload) ? ($payload['tag_name'] ?? null) : null;

        if (! is_string($tag) || ! preg_match('/^v(\d+\.\d+\.\d+)$/', $tag, $match)) {
            return 'latest release is not a stable vX.Y.Z tag';
        }

        if (($payload['prerelease'] ?? false) !== false || ($payload['draft'] ?? false) !== false) {
            return 'latest release is a prerelease or draft';
        }

        Cache::forever(self::CACHE_KEY, ['version' => $match[1], 'tag' => $tag]);

        return null;
    }
}
