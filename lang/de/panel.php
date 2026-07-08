<?php

declare(strict_types=1);

/*
| Operator panel UI strings — German.
| "Widerruf" is the § 356a BGB term, aligned with lang/de/{mail,push,wf}.php.
| Never use "Widerspruch" (a different legal concept).
*/

return [

    'settings' => [
        'navigation_group' => 'Einstellungen',
        'notification' => [
            'navigation_label' => 'Benachrichtigungen',
            'title' => 'Benachrichtigungen',
            'effective' => 'Benachrichtigungen gehen aktuell an: :email',
            'effective_none' => 'Es ist noch kein Empfänger konfiguriert — es werden keine Betreiber-Benachrichtigungen versendet.',
            'email' => [
                'label' => 'Empfängeradresse',
                'help' => 'Adresse, an die neue Widerrufe gemeldet werden. Kann sich von der Absenderadresse unterscheiden (z. B. Versand über no-reply@, Eingang an shop@). Bleibt das Feld leer, wird die Umgebungsvariable MERCHANT_NOTIFICATION_EMAIL und andernfalls die Impressum-E-Mail verwendet.',
            ],
            'test' => [
                'button' => 'Test-Mail senden',
                'sent' => 'Test-Mail an :email gesendet.',
                'failed' => 'Test-Mail konnte nicht gesendet werden.',
                'none' => 'Kein Empfänger konfiguriert — bitte zuerst eine Adresse hinterlegen oder die Impressum-E-Mail setzen.',
                'sample_subject' => 'Testbenachrichtigung (Beispiel)',
            ],
        ],
        'localization' => [
            'navigation_label' => 'Sprachen',
            'title' => 'Sprachen',
            'available' => [
                'label' => 'Angebotene Sprachen',
                'help' => 'Sprachen, die das Widerrufsformular in der Sprachauswahl anbietet. Die Auswahl wird ausgeblendet, wenn nur eine Sprache angeboten wird.',
            ],
            'default' => [
                'label' => 'Standardsprache',
                'help' => 'Wird verwendet, wenn der Verbraucher keine Sprache gewählt hat.',
                'not_available' => 'Die Standardsprache muss eine der angebotenen Sprachen sein.',
            ],
        ],
        'scope' => [
            'navigation_label' => 'Widerrufsumfang',
            'title' => 'Widerrufsumfang',
            'description' => 'Legt fest, welche Vertragsarten das Formular benennt. Diese Auswahl gestaltet nur die Formulartexte — sie schränkt den Widerruf niemals ein. Verbraucher können immer per Freitext angeben, was sie widerrufen möchten.',
            'goods' => [
                'label' => 'Waren',
                'help' => 'Der Shop verkauft körperliche Waren.',
            ],
            'services' => [
                'label' => 'Dienstleistungen',
                'help' => 'Der Shop bietet Dienstleistungen an.',
            ],
            'digital' => [
                'label' => 'Digitale Inhalte',
                'help' => 'Der Shop bietet digitale Inhalte an, etwa Downloads oder Streaming.',
            ],
        ],
        'legal' => [
            'navigation_label' => 'Rechtstexte',
            'title' => 'Rechtstexte',
            'tab_privacy' => 'Datenschutzerklärung',
            'tab_imprint' => 'Impressum',
            'privacy_content' => [
                'label' => 'Datenschutzerklärung',
                'help' => 'Der eigene Datenschutztext je Sprache. Revoco liefert keine vorgefertigten Rechtstexte — für den Inhalt sind Sie als Verantwortlicher zuständig.',
            ],
            'privacy_link' => [
                'label' => 'Externe Datenschutz-URL (überschreibt)',
                'help' => 'Wenn gesetzt, verweist der Fußzeilen-Link dorthin und die interne Seite leitet dorthin weiter. Leer lassen, um den Text oben zu verwenden.',
            ],
            'fallback_order' => [
                'label' => 'Ersatzsprachen-Reihenfolge',
                'help' => 'Sprachen, die der Reihe nach herangezogen werden, wenn die angeforderte Sprache keinen Text hat.',
            ],
            'imprint_link' => [
                'label' => 'Externe Impressum-URL (überschreibt)',
                'help' => 'Wenn gesetzt, verweist der Fußzeilen-Link dorthin und die interne Seite leitet dorthin weiter. Leer lassen, um die Angaben unten zu verwenden.',
            ],
            'imprint_entity' => [
                'label' => 'Angaben zum Unternehmen',
                'help' => 'Name, Rechtsform und gesetzlicher Vertreter gemäß § 5 Abs. 1 Nr. 1 DDG.',
            ],
            'imprint_contact' => [
                'label' => 'Kontakt',
                'help' => 'E-Mail-Adresse und ein zweiter schneller Kontaktweg (§ 5 Abs. 1 Nr. 2 DDG, EuGH C-298/07).',
            ],
            'imprint_register' => [
                'label' => 'Handelsregister',
                'help' => 'Registergericht und Registernummer, sofern eingetragen (§ 5 Abs. 1 Nr. 4 DDG).',
            ],
            'imprint_tax' => [
                'label' => 'Steuerliche Angaben',
                'help' => 'Umsatzsteuer-ID (§ 27a UStG) und/oder Wirtschafts-IdNr. (§ 139c AO), sofern vorhanden (§ 5 Abs. 1 Nr. 6 DDG).',
            ],
            'imprint_professional' => [
                'label' => 'Berufsrechtliche Angaben (optional)',
                'help' => 'Nur für reglementierte Berufe und genehmigungspflichtige Tätigkeiten (§ 5 Abs. 1 Nr. 3 u. 5 DDG).',
            ],
            'imprint_addendum' => [
                'label' => 'Freier Zusatz',
                'help' => 'Freitextfeld je Sprache für weitere Pflichtangaben oder Hinweise (z. B. VSBG-Hinweis auf Anraten des Anwalts).',
            ],
            'imprint_name' => ['label' => 'Name / Firma'],
            'imprint_legal_form' => ['label' => 'Rechtsform'],
            'imprint_represented_by' => ['label' => 'Vertreten durch'],
            'imprint_address' => [
                'label' => 'Anschrift',
                'help' => 'Vollständige Postanschrift (keine Postfachadresse) nach § 5 Abs. 1 Nr. 1 DDG. Je Sprache eintragen, damit Landesbezeichnung oder andere Angaben lokalisiert werden können.',
            ],
            'imprint_email' => ['label' => 'E-Mail-Adresse'],
            'imprint_phone' => ['label' => 'Telefonnummer'],
            'imprint_contact_note' => ['label' => 'Weiterer Kontakthinweis'],
            'imprint_register_court' => ['label' => 'Registergericht'],
            'imprint_register_number' => ['label' => 'Registernummer'],
            'imprint_vat_id' => ['label' => 'Umsatzsteuer-Identifikationsnummer'],
            'imprint_business_id' => ['label' => 'Wirtschafts-Identifikationsnummer'],
            'imprint_supervisory_authority' => ['label' => 'Aufsichtsbehörde'],
            'imprint_chamber' => ['label' => 'Kammer'],
            'imprint_job_title' => ['label' => 'Berufsbezeichnung und Verleihungsstaat'],
            'imprint_professional_rules' => ['label' => 'Berufsrechtliche Regelungen und Fundstelle'],
            'imprint_liquidation_note' => ['label' => 'Abwicklungs-/Insolvenzhinweis (§ 5 Nr. 7 DDG)'],
        ],
    ],

    'editor' => [
        'paste_html' => [
            'tool' => 'HTML einfügen',
            'heading' => 'HTML einfügen',
            'description' => 'HTML hier einfügen (z. B. eine von einer Kanzlei gelieferte Datenschutzerklärung). Der Inhalt wird bereinigt und formatiert in den Editor übernommen.',
            'placeholder' => '<h2>Überschrift</h2><p>Ihr HTML-Text …</p>',
            'submit' => 'Einfügen',
        ],
    ],

    'resource' => [
        'navigation_label' => 'Widerrufe',
        'model_label' => 'Widerruf',
        'plural_model_label' => 'Widerrufe',
    ],

    'column' => [
        'received' => 'Eingegangen',
        'name' => 'Name',
        'order_number' => 'Bestellnr.',
        'no_spam' => 'Kein Spam',
        'handled' => 'Bearbeitet',
    ],

    'filter' => [
        'handled_status' => 'Bearbeitungsstatus',
        'handled_only' => 'Nur bearbeitete',
        'unhandled_only' => 'Nur unbearbeitete',
        'spam_status' => 'Spam-Status',
        'spam_only' => 'Nur Spam',
        'not_spam_only' => 'Kein Spam',
        'date_range' => 'Zeitraum',
        'date_from' => 'Von',
        'date_until' => 'Bis',
    ],

    'action' => [
        'mark_handled' => 'Als bearbeitet markieren',
        'unmark_handled' => 'Markierung aufheben',
    ],

    'setup' => [
        'warning' => 'Einrichtung ausstehend — folgende Seiten fehlen noch: :pages.',
        'link' => 'Rechtstexte einrichten',
        'page_imprint' => 'Impressum',
        'page_privacy' => 'Datenschutzerklärung',
    ],

    'infolist' => [
        'section' => [
            'submitter' => 'Angaben zum Einreicher',
            'statement' => 'Widerrufserklärung',
            'triage' => 'Triage',
            'status' => 'Status & Zeitstempel',
        ],
        'field' => [
            'name' => 'Name',
            'email' => 'E-Mail',
            'order_number' => 'Bestell-/Vertragsnummer',
            'locale' => 'Sprache',
            'subject' => 'Betreffende Ware / Dienstleistung',
            'spam_signal' => 'Spam-Signal',
            'spam_reason' => 'Spam-Grund',
            'handled_at' => 'Bearbeitet am',
            'received_at' => 'Eingegangen am',
            'last_updated' => 'Zuletzt aktualisiert',
        ],
        'spam' => [
            'yes' => 'Spam',
            'no' => 'Kein Spam',
        ],
        'not_handled' => 'Nicht bearbeitet',
    ],

];
