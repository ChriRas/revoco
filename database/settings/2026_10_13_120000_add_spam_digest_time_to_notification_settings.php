<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Adds the time the daily spam digest is sent (slice-026). Defaults to 07:00 in the
 * app timezone until the operator changes it on the Filament notifications page.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notification.spam_digest_time', '07:00');
    }

    public function down(): void
    {
        $this->migrator->delete('notification.spam_digest_time');
    }
};
