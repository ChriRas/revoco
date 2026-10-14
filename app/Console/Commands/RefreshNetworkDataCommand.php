<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Network\NetworkDataRefresher;
use Illuminate\Console\Command;

/**
 * Downloads the network data the spam signals read locally: DB-IP Lite country + ASN, the
 * listed-network feeds and the Tor exit list. Scheduled daily; also run by hand (first run
 * after a deploy) or by the panel's "Refresh now". Takes options only, so it is safe under
 * --no-interaction. A failing source keeps its previous file and makes the exit code non-zero.
 */
final class RefreshNetworkDataCommand extends Command
{
    protected $signature = 'revoco:network-data:refresh
        {--only= : Comma-separated sources to refresh: geo, drop, asn-drop, tor (default: all)}';

    protected $description = 'Refresh the local country / ASN databases and the listed-network feeds.';

    public function handle(NetworkDataRefresher $refresher): int
    {
        $option = $this->option('only');
        $only = array_values(array_filter(array_map('trim', explode(',', is_string($option) ? $option : ''))));
        $unknown = array_diff($only, NetworkDataRefresher::SOURCES);

        if ($unknown !== []) {
            $this->error('Unknown source: '.implode(', ', $unknown).'. Choose from: '.implode(', ', NetworkDataRefresher::SOURCES).'.');

            return self::INVALID;
        }

        $failed = false;

        foreach ($refresher->refresh($only) as $source => $error) {
            if ($error === null) {
                $this->line("{$source}: ok");
            } else {
                $failed = true;
                $this->error("{$source}: failed ({$error})");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
