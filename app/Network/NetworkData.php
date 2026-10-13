<?php

declare(strict_types=1);

namespace App\Network;

use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * The downloaded network files under `revoco.network.path`: the two MMDBs, the normalised
 * feed lists and `meta.json`. Feed lists load lazily, once per instance, and every reader
 * answers "empty" for a missing or unparsable file, so the submit never fails on them.
 */
final class NetworkData
{
    public const string COUNTRY_DB = 'dbip-country-lite.mmdb';

    public const string ASN_DB = 'dbip-asn-lite.mmdb';

    public const string DROP = 'drop.json';

    public const string ASN_DROP = 'asn-drop.json';

    public const string TOR = 'tor-exits.txt';

    public const string META = 'meta.json';

    /** @var array{v4: list<string>, v6: list<string>}|null */
    private ?array $drop = null;

    /** @var array<int, true>|null */
    private ?array $asnDrop = null;

    /** @var array<array-key, true>|null */
    private ?array $tor = null;

    public function directory(): string
    {
        return Config::string('revoco.network.path');
    }

    public function path(string $file): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$file;
    }

    /** Drops the loaded lists, so the next read goes to disk again (after a refresh). */
    public function flush(): void
    {
        $this->drop = $this->asnDrop = $this->tor = null;
    }

    /**
     * Per source key (`geo`, `drop`, `asn-drop`, `tor`): fetched_at, records, source.
     *
     * @return array<string, array{fetched_at?: string, records?: int, source?: string|null}>
     */
    public function meta(): array
    {
        /** @var array<string, array{fetched_at?: string, records?: int, source?: string|null}> */
        return $this->readJson(self::META) ?? [];
    }

    /** Whether the IP lies inside one of the listed IP blocks. */
    public function isInDrop(string $ip): bool
    {
        $this->drop ??= $this->loadDrop();
        $blocks = str_contains($ip, ':') ? $this->drop['v6'] : $this->drop['v4'];

        try {
            return $blocks !== [] && IpUtils::checkIp($ip, $blocks);
        } catch (Throwable) {
            return false;
        }
    }

    public function isAsnListed(int $asn): bool
    {
        $this->asnDrop ??= $this->loadAsnDrop();

        return isset($this->asnDrop[$asn]);
    }

    public function isTorExit(string $ip): bool
    {
        $this->tor ??= $this->loadTor();
        $packed = @inet_pton($ip);

        return $packed !== false && isset($this->tor[inet_ntop($packed)]);
    }

    /** @return array{v4: list<string>, v6: list<string>} */
    private function loadDrop(): array
    {
        $data = $this->readJson(self::DROP);
        $drop = ['v4' => [], 'v6' => []];

        foreach (['v4', 'v6'] as $family) {
            foreach (is_array($data) && is_array($data[$family] ?? null) ? $data[$family] : [] as $cidr) {
                if (is_string($cidr)) {
                    $drop[$family][] = $cidr;
                }
            }
        }

        return $drop;
    }

    /** @return array<int, true> */
    private function loadAsnDrop(): array
    {
        $set = [];

        foreach ($this->readJson(self::ASN_DROP) ?? [] as $asn) {
            if (is_int($asn)) {
                $set[$asn] = true;
            }
        }

        return $set;
    }

    /** @return array<array-key, true> */
    private function loadTor(): array
    {
        $path = $this->path(self::TOR);
        $lines = is_file($path) ? @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        $set = [];

        foreach ($lines === false ? [] : $lines as $line) {
            $packed = @inet_pton(trim($line));

            if ($packed !== false) {
                $set[inet_ntop($packed)] = true;
            }
        }

        return $set;
    }

    /** @return array<mixed>|null */
    private function readJson(string $file): ?array
    {
        $path = $this->path($file);
        $raw = is_file($path) ? @file_get_contents($path) : false;

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
