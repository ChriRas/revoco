<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Seeds the locale settings from the deployment's existing configuration so an
 * upgrade preserves the previously configured switcher behaviour. Mirrors the
 * retired config/app.php computation: APP_AVAILABLE_LOCALES (comma-separated,
 * falling back to APP_LOCALE) for the offered set, APP_LOCALE for the default.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $configuredDefault = config('app.locale');
        $default = is_string($configuredDefault) ? $configuredDefault : 'de';

        /*
         * APP_AVAILABLE_LOCALES has no config binding (it was retired with this
         * slice); read it straight from the environment so the upgrade seed is
         * behaviour-preserving. Reliable here because docker/entrypoint.sh runs
         * migrations before config:cache, so env() reflects the live value.
         */
        $configured = env('APP_AVAILABLE_LOCALES', $default); // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        $raw = is_string($configured) ? $configured : $default;

        /** @var list<string> $available */
        $available = [];

        foreach (explode(',', $raw) as $code) {
            $code = trim($code);

            if ($code !== '') {
                $available[] = $code;
            }
        }

        if ($available === []) {
            $available = [$default];
        }

        $this->migrator->add('locale.available', $available);
        $this->migrator->add('locale.default', $default);
    }
};
