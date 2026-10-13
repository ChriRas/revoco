<?php

declare(strict_types=1);

namespace App\Network;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Downloads the DB-IP Lite country + ASN databases, the listed-network feeds (IP blocks, ASNs)
 * and the Tor exit list into `revoco.network.path`.
 *
 * Every source is downloaded, validated and written to a temp file, then swapped in with an
 * atomic rename. A failing source keeps its previous file, is logged, and does not stop the
 * others. `meta.json` records per source when it was fetched, how many entries it holds and
 * where it came from.
 */
final class NetworkDataRefresher
{
    public const string GEO = 'geo';

    public const string DROP = 'drop';

    public const string ASN_DROP = 'asn-drop';

    public const string TOR = 'tor';

    /** @var list<string> */
    public const array SOURCES = [self::GEO, self::DROP, self::ASN_DROP, self::TOR];

    public function __construct(private readonly NetworkData $data) {}

    /**
     * @param  list<string>  $only  Source keys to refresh; empty = all.
     * @return array<string, string|null> Per refreshed source: null on success, else the error message.
     */
    public function refresh(array $only = []): array
    {
        $sources = $only === [] ? self::SOURCES : array_values(array_intersect(self::SOURCES, $only));

        try {
            // One refresh at a time: the scheduled run and the panel's job both rewrite meta.json.
            /** @var array<string, string|null> $results */
            $results = Cache::lock('revoco:network-data-refresh', 300)->block(120, fn (): array => $this->run($only));

            return $results;
        } catch (LockTimeoutException) {
            return array_fill_keys($sources, 'Another refresh is still running.');
        }
    }

    /**
     * @param  list<string>  $only
     * @return array<string, string|null>
     */
    private function run(array $only): array
    {
        File::ensureDirectoryExists($this->data->directory());

        $results = [];

        foreach (self::SOURCES as $source) {
            if ($only !== [] && ! in_array($source, $only, true)) {
                continue;
            }

            try {
                $this->{match ($source) {
                    self::GEO => 'refreshGeo',
                    self::DROP => 'refreshDrop',
                    self::ASN_DROP => 'refreshAsnDrop',
                    self::TOR => 'refreshTor',
                }}();
                $results[$source] = null;
            } catch (Throwable $e) {
                Log::warning('Network data refresh failed for source "'.$source.'": '.$e->getMessage());
                $results[$source] = $e->getMessage();
            }
        }

        $this->data->flush();

        return $results;
    }

    /** Current month first, the previous month when it is not published yet (404). Skipped when already current. */
    private function refreshGeo(): void
    {
        $stored = $this->data->meta()[self::GEO]['source'] ?? null;
        $month = now()->toImmutable()->utc()->startOfMonth();

        foreach ([$month, $month->subMonth()] as $candidate) {
            $label = $candidate->format('Y-m');

            if (is_string($stored) && $stored >= $label && is_file($this->data->path(NetworkData::COUNTRY_DB)) && is_file($this->data->path(NetworkData::ASN_DB))) {
                return;
            }

            $country = $this->get($this->geoUrl('country', $label), allowNotFound: true);
            $asn = $country === null ? null : $this->get($this->geoUrl('asn', $label), allowNotFound: true);

            if ($country === null || $asn === null) {
                continue;
            }

            $country = $this->gunzip($country);
            $asn = $this->gunzip($asn);
            $this->assertMmdb($country, 'country');
            $nodes = $this->assertMmdb($asn, 'asn');

            $this->swap([NetworkData::COUNTRY_DB => $country, NetworkData::ASN_DB => $asn]);
            $this->record(self::GEO, $nodes, $label);

            return;
        }

        throw new NetworkSourceException('No DB-IP database is published for the current or the previous month.');
    }

    private function refreshDrop(): void
    {
        $rows4 = $this->ndjson($this->body(Config::string('revoco.network.feeds.drop_v4')));
        $rows6 = $this->ndjson($this->body(Config::string('revoco.network.feeds.drop_v6')));
        $v4 = $this->cidrs($rows4, false);
        $v6 = $this->cidrs($rows6, true);

        $this->swap([NetworkData::DROP => json_encode(['v4' => $v4, 'v6' => $v6], JSON_THROW_ON_ERROR)]);
        $this->record(self::DROP, count($v4) + count($v6), $this->feedTimestamp($rows4));
    }

    private function refreshAsnDrop(): void
    {
        $asns = [];

        $rows = $this->ndjson($this->body(Config::string('revoco.network.feeds.asn_drop')));

        foreach ($rows as $row) {
            if (isset($row['asn']) && is_int($row['asn']) && $row['asn'] > 0) {
                $asns[] = $row['asn'];
            } elseif (($row['type'] ?? null) !== 'metadata') {
                throw new NetworkSourceException('Unexpected entry in the ASN list.');
            }
        }

        if ($asns === []) {
            throw new NetworkSourceException('The ASN list is empty.');
        }

        $this->swap([NetworkData::ASN_DROP => json_encode($asns, JSON_THROW_ON_ERROR)]);
        $this->record(self::ASN_DROP, count($asns), $this->feedTimestamp($rows));
    }

    private function refreshTor(): void
    {
        $ips = [];

        foreach (preg_split('/\R/', $this->body(Config::string('revoco.network.feeds.tor'))) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (filter_var($line, FILTER_VALIDATE_IP) === false) {
                throw new NetworkSourceException('The Tor exit list holds an invalid address.');
            }

            $ips[] = $line;
        }

        if ($ips === []) {
            throw new NetworkSourceException('The Tor exit list is empty.');
        }

        $this->swap([NetworkData::TOR => implode("\n", $ips)."\n"]);
        $this->record(self::TOR, count($ips));
    }

    private function geoUrl(string $type, string $month): string
    {
        return str_replace(['{type}', '{month}'], [$type, $month], Config::string('revoco.network.geo.url'));
    }

    /** The response body; null for a 404 when the caller can fall back, an exception for every other failure. */
    private function get(string $url, bool $allowNotFound = false): ?string
    {
        try {
            $response = Http::timeout(Config::integer('revoco.network.timeout'))->get($url);
        } catch (Throwable $e) {
            throw new NetworkSourceException('Download failed: '.$e->getMessage(), previous: $e);
        }

        return $this->checked($response, $allowNotFound);
    }

    private function body(string $url): string
    {
        return $this->get($url) ?? throw new NetworkSourceException('Download failed.');
    }

    private function checked(Response $response, bool $allowNotFound): ?string
    {
        if ($allowNotFound && $response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new NetworkSourceException('Download failed with HTTP '.$response->status().'.');
        }

        return $response->body();
    }

    private function gunzip(string $gz): string
    {
        $raw = @gzdecode($gz);

        if ($raw === false || $raw === '') {
            throw new NetworkSourceException('The downloaded archive is corrupt.');
        }

        return $raw;
    }

    /** Opens the MMDB and returns its node count; anything unreadable or of the wrong type is rejected. */
    private function assertMmdb(string $raw, string $type): int
    {
        $path = $this->data->directory().DIRECTORY_SEPARATOR.'validate-'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($path, $raw);

        try {
            $reader = new Reader($path);
            $metadata = $reader->metadata();
            $reader->close();
        } catch (Throwable $e) {
            throw new NetworkSourceException('The '.$type.' database cannot be read: '.$e->getMessage(), previous: $e);
        } finally {
            @unlink($path);
        }

        if (stripos($metadata->databaseType, $type) === false) {
            throw new NetworkSourceException('The '.$type.' database has an unexpected type "'.$metadata->databaseType.'".');
        }

        return $metadata->nodeCount;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string> Valid CIDRs of the NDJSON feed; an empty or invalid feed is rejected.
     */
    private function cidrs(array $rows, bool $ipv6): array
    {
        $cidrs = [];

        foreach ($rows as $row) {
            if (($row['type'] ?? null) === 'metadata') {
                continue;
            }

            $cidr = $row['cidr'] ?? null;

            if (! is_string($cidr) || ! $this->isCidr($cidr, $ipv6)) {
                throw new NetworkSourceException('The IP block list holds an invalid entry.');
            }

            $cidrs[] = $cidr;
        }

        if ($cidrs === []) {
            throw new NetworkSourceException('The IP block list is empty.');
        }

        return $cidrs;
    }

    /**
     * The feed's own timestamp from its trailing metadata line, as the record's source label.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function feedTimestamp(array $rows): ?string
    {
        foreach ($rows as $row) {
            if (($row['type'] ?? null) === 'metadata' && is_int($row['timestamp'] ?? null)) {
                return gmdate('Y-m-d\TH:i:s\Z', $row['timestamp']);
            }
        }

        return null;
    }

    private function isCidr(string $cidr, bool $ipv6): bool
    {
        $parts = explode('/', $cidr);

        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return false;
        }

        $flag = $ipv6 ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;

        return filter_var($parts[0], FILTER_VALIDATE_IP, $flag) !== false && (int) $parts[1] <= ($ipv6 ? 128 : 32);
    }

    /** @return list<array<string, mixed>> */
    private function ndjson(string $body): array
    {
        $rows = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = json_decode($line, true);

            if (! is_array($row)) {
                throw new NetworkSourceException('The feed is not valid NDJSON.');
            }

            /** @var array<string, mixed> $row */
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Writes every file to a temp name first, then renames them all into place, so a failure while writing
     * leaves the previous files untouched.
     *
     * @param  array<string, string>  $files  file name => contents
     */
    private function swap(array $files): void
    {
        $temps = [];

        try {
            foreach ($files as $name => $contents) {
                $temp = $this->data->path($name.'.'.bin2hex(random_bytes(4)).'.tmp');

                if (file_put_contents($temp, $contents) === false) {
                    throw new NetworkSourceException('Cannot write '.$name.'.');
                }

                $temps[$name] = $temp;
            }

            foreach ($temps as $name => $temp) {
                if (! rename($temp, $this->data->path($name))) {
                    throw new NetworkSourceException('Cannot replace '.$name.'.');
                }

                unset($temps[$name]);
            }
        } finally {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
        }
    }

    private function record(string $source, int $records, ?string $label = null): void
    {
        $meta = $this->data->meta();
        $meta[$source] = [
            'fetched_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'records' => $records,
            'source' => $label,
        ];

        $this->swap([NetworkData::META => json_encode($meta, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)]);
    }
}
