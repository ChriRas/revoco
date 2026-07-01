<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Adds the § 5 DDG imprint fields to the legal settings group (slice-014).
 * All fields default to null/empty — a fresh install shows the neutral
 * "not configured yet" placeholder until the operator fills in the panel.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('legal.imprint_name', null);
        $this->migrator->add('legal.imprint_legal_form', null);
        $this->migrator->add('legal.imprint_represented_by', null);
        $this->migrator->add('legal.imprint_address', null);
        $this->migrator->add('legal.imprint_email', null);
        $this->migrator->add('legal.imprint_phone', null);
        $this->migrator->add('legal.imprint_contact_note', null);
        $this->migrator->add('legal.imprint_register_court', null);
        $this->migrator->add('legal.imprint_register_number', null);
        $this->migrator->add('legal.imprint_vat_id', null);
        $this->migrator->add('legal.imprint_business_id', null);
        $this->migrator->add('legal.imprint_supervisory_authority', null);
        $this->migrator->add('legal.imprint_chamber', null);
        $this->migrator->add('legal.imprint_job_title', null);
        $this->migrator->add('legal.imprint_professional_rules', null);
        $this->migrator->add('legal.imprint_liquidation_note', null);
        $this->migrator->add('legal.imprint_addendum', []);
        $this->migrator->add('legal.imprint_link', null);
    }
};
