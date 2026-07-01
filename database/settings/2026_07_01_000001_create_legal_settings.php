<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Seeds the legal settings with empty defaults: a fresh install ships no legal
 * content and no override link, so the consumer page shows the neutral "not
 * configured yet" placeholder until the operator fills it in. Only the fallback
 * chain is seeded — with the deployment's default locale, so a request in an
 * unconfigured locale resolves to the default one once content exists.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $configuredDefault = config('app.locale');
        $default = is_string($configuredDefault) ? $configuredDefault : 'de';

        $this->migrator->add('legal.privacy_content', []);
        $this->migrator->add('legal.privacy_link', null);
        $this->migrator->add('legal.fallback_order', [$default]);
    }
};
