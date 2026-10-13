<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AcknowledgmentTrigger;
use App\Events\WithdrawalMarkedNotSpam;
use App\Mail\WithdrawalAcknowledgment;
use App\Models\AcknowledgmentLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the acknowledgment a "not spam" correction releases: red rows got none at
 * submit. The log claim makes it happen at most once per row, whichever path
 * (mail link, panel row action, bulk) fired the event. The mail shows the original
 * receipt time, since it renders the stored `created_at`.
 */
final class SendAcknowledgmentOnNotSpam implements ShouldQueue
{
    use Queueable;

    /** A row purged before the listener runs needs no acknowledgment. */
    public bool $deleteWhenMissingModels = true;

    public function handle(WithdrawalMarkedNotSpam $event): void
    {
        $withdrawal = $event->withdrawal;

        if (! AcknowledgmentLog::claim($withdrawal, AcknowledgmentTrigger::NotSpam)) {
            return;
        }

        rescue(fn () => Mail::to($withdrawal->email)
            ->send((new WithdrawalAcknowledgment($withdrawal))->locale($withdrawal->locale)));
    }
}
