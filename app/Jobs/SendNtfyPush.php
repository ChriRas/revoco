<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Ntfy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $ntfy->publish(
            message: $this->spam ? __('push.body_spam') : __('push.body'),
            title: __('push.title'),
            tags: $this->spam ? ['warning'] : ['envelope'],
        );
    }
}
