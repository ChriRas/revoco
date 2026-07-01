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
            'invalid' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
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

    'language' => [
        'label' => 'Sprache wählen',
        // Autonyms — each language in its own name, identical across locales.
        'names' => [
            'de' => 'Deutsch',
            'en' => 'English',
        ],
    ],

    'submit' => 'Widerruf bestätigen',
    'hint' => 'Mit »Pflichtfeld« markierte Felder sind erforderlich.',

    'honeypot' => [
        'label' => 'Dieses Feld bitte frei lassen.',
    ],

    'success' => [
        'title' => 'Eingang Ihres Widerrufs bestätigt',
        'body' => 'Ihre Widerrufserklärung ist bei uns eingegangen und wird geprüft.',
        'note' => 'Sie können dieses Fenster jetzt schließen.',
    ],

    'footer' => [
        'imprint' => 'Impressum',
        'privacy' => 'Datenschutzerklärung',
        'source' => 'Quelltext',
    ],

    'legal' => [
        'privacy' => [
            'title' => 'Datenschutzerklärung',
        ],
        // Neutral hint shown when the operator has not configured the page yet —
        // deliberately NOT legal text (no Lorem Ipsum, nothing that could read as valid).
        'placeholder' => 'Diese Seite wurde noch nicht eingerichtet.',
    ],

];
