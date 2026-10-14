<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Adds when the last spam digest was queued (slice-032): the next digest covers everything since
 * then (at most 7 days back), so a skipped run loses nothing. Null until the first digest.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notification.spam_digest_last_sent_at', null);
    }

    public function down(): void
    {
        $this->migrator->delete('notification.spam_digest_last_sent_at');
    }
};
