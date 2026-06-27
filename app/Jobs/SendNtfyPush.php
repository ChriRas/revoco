<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Ntfy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

/**
 * Publishes the data-minimal operator push for a new withdrawal.
 *
 * The payload carries ONLY the triage signal (spam yes/no) — never name,
 * e-mail, order number or subject. Its own queued job so a push failure never
 * blocks the e-mails.
 */
final class SendNtfyPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly bool $spam) {}

    public function handle(Ntfy $ntfy): void
    {
        // Operator-facing push — pinned to the FROZEN default locale (not
        // config('app.locale'), which app()->setLocale() rewrites to the
        // consumer's choice), so it never follows a consumer's language switch.
        $locale = Config::string('app.default_locale');

        $ntfy->publish(
            message: $this->spam ? __('push.body_spam', [], $locale) : __('push.body', [], $locale),
            title: __('push.title', [], $locale),
            tags: $this->spam ? ['warning'] : ['envelope'],
        );
    }
}
