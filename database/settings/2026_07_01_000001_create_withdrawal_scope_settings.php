<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Seeds the withdrawal-scope toggles all OFF, so an upgrade keeps today's generic
 * form copy (behaviour-preserving). The operator opts into naming specific
 * categories on the Filament "Withdrawal scope" page. Display only — no effect on
 * the submit path (§ 356a).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('withdrawal_scope.offers_goods', false);
        $this->migrator->add('withdrawal_scope.offers_services', false);
        $this->migrator->add('withdrawal_scope.offers_digital', false);
    }
};
