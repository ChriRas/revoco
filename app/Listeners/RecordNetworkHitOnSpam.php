<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WithdrawalConfirmedAsSpam;
use App\Models\SpamNetworkHit;

/**
 * Learns the network of a confirmed spam row: one hit per row, refreshed when the row is
 * confirmed again. Rows without an ASN are skipped. All confirm paths fire the event, so
 * the panel action, the bulk action and the mail link are covered here.
 */
final class RecordNetworkHitOnSpam
{
    public function handle(WithdrawalConfirmedAsSpam $event): void
    {
        $withdrawal = $event->withdrawal;

        if ($withdrawal->asn === null) {
            return;
        }

        SpamNetworkHit::query()->updateOrCreate(
            ['withdrawal_id' => $withdrawal->id],
            ['asn' => $withdrawal->asn, 'confirmed_at' => now()],
        );
    }
}
