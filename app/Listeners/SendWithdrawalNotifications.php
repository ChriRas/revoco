<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WithdrawalSubmitted;
use App\Jobs\SendNtfyPush;
use App\Mail\WithdrawalAcknowledgment;
use App\Mail\WithdrawalNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Fans the WithdrawalSubmitted event out to the delivery channels.
 *
 * The listener itself is queued, so ALL delivery (even the fan-out) runs off the
 * request — the submit never waits on or fails because of SMTP/push. Each channel
 * is then its own queued job (mailables + push job, all ShouldQueue), and each
 * dispatch is wrapped in rescue() so one channel failing never blocks the others.
 */
final class SendWithdrawalNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(WithdrawalSubmitted $event): void
    {
        $withdrawal = $event->withdrawal;

        // Consumer acknowledgment — ALWAYS, even for spam-flagged rows (§ 356a Abs. 4),
        // rendered in the consumer's locale.
        rescue(fn () => Mail::to($withdrawal->email)
            ->send((new WithdrawalAcknowledgment($withdrawal))->locale($withdrawal->locale)));

        // Merchant notification — always, when an operator address is configured.
        $merchant = config('revoco.merchant_email');
        if (is_string($merchant) && $merchant !== '') {
            rescue(fn () => Mail::to($merchant)->send(new WithdrawalNotification($withdrawal)));
        }

        // ntfy push — opt-in, data-minimal (no PII).
        if (config('revoco.ntfy.enabled') === true) {
            rescue(fn () => SendNtfyPush::dispatch($withdrawal->spam));
        }
    }
}
