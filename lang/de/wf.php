<?php

declare(strict_types=1);

/*
| User-facing copy for the withdrawal form (German — the launch language).
| End-user UI text is the one committed-artifact exception to the English rule.
| Every key the Blade view references must exist here: a missing key renders the
| literal "wf.*" identifier, which the feature test rejects.
*/

return [

    'title' => 'Widerrufsformular',
    'subtitle' => 'Bitte füllen Sie die folgenden Felder aus.',

    'field' => [
        'name' => [
            'label' => 'Ihr Vor- und Nachname',
            'error' => 'Bitte geben Sie Ihren Vor- und Nachnamen ein.',
        ],
        'email' => [
            'label' => 'E-Mail-Adresse',
            'error' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
        ],
        'order' => [
            'label' => 'Bestellnummer / Vertragsnummer',
        ],
        'subject' => [
            'label' => 'Betreffende Ware, digitale Inhalte oder Dienstleistung',
            'error' => 'Bitte geben Sie an, welche Ware, digitalen Inhalte oder Dienstleistung Sie widerrufen möchten.',
        ],
    ],

    'badge' => [
        'required' => 'Pflichtfeld',
        'optional' => 'optional',
    ],

    'submit' => 'Widerruf bestätigen',
    'hint' => 'Mit »Pflichtfeld« markierte Felder sind erforderlich.',

    'honeypot' => [
        'label' => 'Dieses Feld bitte frei lassen.',
    ],

    'footer' => [
        'imprint' => 'Impressum',
        'privacy' => 'Datenschutzerklärung',
    ],

];
