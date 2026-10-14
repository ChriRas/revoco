<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WithdrawalMarkedNotSpam;
use App\Models\SpamNetworkHit;

/** A "not spam" correction takes the row's hit back; a row that was never confirmed has none (no-op). */
final class ForgetNetworkHitOnNotSpam
{
    public function handle(WithdrawalMarkedNotSpam $event): void
    {
        SpamNetworkHit::query()->whereKey($event->withdrawal->id)->delete();
    }
}
