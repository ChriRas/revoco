<?php

declare(strict_types=1);

namespace App\Network;

use MaxMind\Db\Reader;
use Throwable;

/**
 * Reads the DB-IP Lite country and ASN databases (MMDB) that `revoco:network-data:refresh`
 * stores. A private or reserved IP, a missing or corrupt database or an unknown address
 * resolves to nothing instead of failing; the country and the ASN are looked up independently.
 */
final class MmdbNetworkLookup implements NetworkLookup
{
    public function __construct(private readonly NetworkData $data) {}

    public function lookup(?string $ip): NetworkInfo
    {
        if ($ip === null || ! $this->isPublic($ip)) {
            return NetworkInfo::unknown();
        }

        $country = $this->country($ip);
        [$asn, $asnOrg] = $this->asn($ip);

        return new NetworkInfo($country, $asn, $asnOrg);
    }

    private function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function country(string $ip): ?string
    {
        $record = $this->record(NetworkData::COUNTRY_DB, $ip);
        $country = is_array($record) && is_array($record['country'] ?? null) ? ($record['country']['iso_code'] ?? null) : null;

        return is_string($country) && preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
    }

    /** @return array{0: int|null, 1: string|null} */
    private function asn(string $ip): array
    {
        $record = $this->record(NetworkData::ASN_DB, $ip);

        if (! is_array($record)) {
            return [null, null];
        }

        $asn = $record['autonomous_system_number'] ?? null;
        $org = $record['autonomous_system_organization'] ?? null;

        return [
            is_int($asn) && $asn > 0 ? $asn : null,
            is_string($org) && $org !== '' ? mb_substr($org, 0, 255) : null,
        ];
    }

    private function record(string $file, string $ip): mixed
    {
        $path = $this->data->path($file);

        if (! is_file($path)) {
            return null;
        }

        try {
            $reader = new Reader($path);

            try {
                return $reader->get($ip);
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            return null;
        }
    }
}
