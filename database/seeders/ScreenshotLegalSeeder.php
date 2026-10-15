<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Settings\LegalSettings;
use Illuminate\Database\Seeder;

/**
 * Invented placeholder imprint and privacy copy for the documentation screenshots, so the setup banner
 * disappears. Kept apart from {@see ScreenshotSeeder} because the warning-banner shot needs the legal
 * settings empty. Sample text for the fictitious "Muster Shop GmbH": no real legal text, no real address.
 * Refuses to run in production and is kept out of the production image (`.dockerignore`).
 */
final class ScreenshotLegalSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            // Unset when the seeder runs outside artisan.
            $this->command?->error('ScreenshotLegalSeeder refuses to run in production.'); // @phpstan-ignore nullsafe.neverNull

            return;
        }

        $legal = app(LegalSettings::class);

        $legal->imprint_name = 'Muster Shop GmbH';
        $legal->imprint_legal_form = 'GmbH';
        $legal->imprint_represented_by = 'Max Mustermann';
        $legal->imprint_address = [
            'de' => "Musterstraße 1\n12345 Musterstadt\nDeutschland",
            'en' => "Musterstraße 1\n12345 Musterstadt\nGermany",
        ];
        $legal->imprint_email = 'info@example.com';
        $legal->imprint_phone = '+49 123 4567890';
        $legal->imprint_register_court = 'Amtsgericht Musterstadt';
        $legal->imprint_register_number = 'HRB 000000';
        $legal->imprint_vat_id = 'DE000000000';
        $legal->privacy_content = [
            'de' => '<h2>Datenschutzerklärung</h2><p>Dies ist ein Beispieltext für die Dokumentation. Er enthält keine echten rechtlichen Angaben.</p>'
                .'<h3>Verantwortlicher</h3><p>Muster Shop GmbH, Musterstraße 1, 12345 Musterstadt.</p>'
                .'<h3>Verarbeitete Daten</h3><p>Name, E-Mail-Adresse und die Angaben zur Bestellung, soweit sie im Widerrufsformular eingegeben werden.</p>',
            'en' => '<h2>Privacy policy</h2><p>This is sample text for the documentation. It contains no real legal information.</p>'
                .'<h3>Controller</h3><p>Muster Shop GmbH, Musterstraße 1, 12345 Musterstadt.</p>'
                .'<h3>Data processed</h3><p>Name, e-mail address and the order details, as far as they are entered in the withdrawal form.</p>',
        ];

        $legal->save();
    }
}
