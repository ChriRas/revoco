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
