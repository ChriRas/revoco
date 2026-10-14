<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Shipping countries default to Germany: a submit from elsewhere only adds a near-neutral signal. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('network_signals.shipping_countries', ['DE']);
    }

    public function down(): void
    {
        $this->migrator->delete('network_signals.shipping_countries');
    }
};
