<?php

declare(strict_types=1);

/*
| Operator panel UI strings — German.
| "Widerruf" is the § 356a BGB term, aligned with lang/de/{mail,push,wf}.php.
| Never use "Widerspruch" (a different legal concept).
*/

return [

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
