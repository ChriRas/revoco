<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Delivery\ChannelPolicy;
use App\Enums\AcknowledgmentTrigger;
use App\Events\WithdrawalSubmitted;
use App\Jobs\SendNtfyPush;
use App\Mail\WithdrawalAcknowledgment;
use App\Mail\WithdrawalNotification;
use App\Models\AcknowledgmentLog;
use App\Support\NotificationRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Fans the WithdrawalSubmitted event out to the delivery channels.
 *
 * The listener itself is queued, so ALL delivery (even the fan-out) runs off the
 * request — the submit never waits on or fails because of SMTP/push. Each channel
 * is then its own queued job (mailables + push job, all ShouldQueue), and each
 * dispatch is wrapped in rescue() so one channel failing never blocks the others.
 * Which channels a row reaches follows its spam level (see ChannelPolicy).
 */
final class SendWithdrawalNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(WithdrawalSubmitted $event): void
    {
        $withdrawal = $event->withdrawal;
        $policy = ChannelPolicy::for($withdrawal->spam_level);

        /**
         * Consumer acknowledgment (§ 356a Abs. 4), rendered in the consumer's locale —
         * withheld for red rows: bots submit real third parties' addresses, so
         * the acknowledgment would turn the operator into a spam sender. The row itself
         * is stored either way. A "not spam" correction sends it later, once.
         */
        if ($policy->acknowledgment && AcknowledgmentLog::claim($withdrawal, AcknowledgmentTrigger::Submit)) {
            rescue(fn () => Mail::to($withdrawal->email)
                ->send((new WithdrawalAcknowledgment($withdrawal))->locale($withdrawal->locale)));
        }

        // Merchant notification — when a recipient resolves (panel setting →
        // MERCHANT_NOTIFICATION_EMAIL env → imprint e-mail; see NotificationRecipient).
        // Pinned to the FROZEN default locale (not config('app.locale'), which
        // app()->setLocale() rewrites to the consumer's choice): the merchant is a
        // single operator whose notifications must not follow a consumer's switch.
        if ($policy->merchantMail) {
            $merchant = NotificationRecipient::resolve();
            if ($merchant !== null) {
                $appLocale = Config::string('app.default_locale');
                rescue(fn () => Mail::to($merchant)->send((new WithdrawalNotification($withdrawal))->locale($appLocale)));
            }
        }

        // ntfy push — opt-in, data-minimal (no PII).
        if ($policy->push && config('revoco.ntfy.enabled') === true) {
            rescue(fn () => SendNtfyPush::dispatch($policy->suspected));
        }
    }
}
