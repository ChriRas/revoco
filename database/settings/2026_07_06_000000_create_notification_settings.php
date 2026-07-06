<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Adds the operator notification recipient (slice-021). Empty by default — the
 * recipient falls back to the MERCHANT_NOTIFICATION_EMAIL env override and then
 * the § 5 DDG imprint e-mail until the operator sets one on the Filament panel.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notification.notification_email', null);
    }
};
