<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Network\NetworkDataRefresher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Runs the network data refresh off the request, for the panel's "Refresh now" action. */
final class RefreshNetworkData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /** Below the queue's `retry_after` (90 s), so the job is never handed out again while it runs. */
    public int $timeout = 85;

    public function handle(NetworkDataRefresher $refresher): void
    {
        $refresher->refresh();
    }
}
