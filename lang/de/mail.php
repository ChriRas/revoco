<?php

declare(strict_types=1);

/*
| E-mail copy. Consumer-facing acknowledgment in the consumer's language;
| the merchant notification is operator-facing (app default locale).
| The acknowledgment confirms RECEIPT only and carries NO advertising (§ 356a Abs. 4).
*/

return [

    'uhr' => 'Uhr',
    'timezone' => 'Europe/Berlin',

    'field' => [
        'name' => 'Name',
        'email' => 'E-Mail-Adresse',
        'order' => 'Bestell-/Vertragsnummer',
        'subject' => 'Betroffene Ware / digitale Inhalte / Dienstleistung',
        'datetime' => 'Eingegangen am',
    ],

    'ack' => [
        'subject' => 'Eingangsbestätigung Ihres Widerrufs',
        'heading' => 'Eingang Ihres Widerrufs bestätigt',
        'greeting' => 'Guten Tag :name,',
        'intro' => 'hiermit bestätigen wir den Eingang Ihrer nachfolgenden Widerrufserklärung. Diese E-Mail dokumentiert ausschließlich den Eingang Ihrer Erklärung.',
        'declaration_heading' => 'Ihre Angaben',
        'outro' => 'Ihr Anliegen wird nun bearbeitet. Bei Rückfragen können Sie auf diese E-Mail antworten.',
    ],

    'notification' => [
        'subject' => 'Neuer Widerruf eingegangen',
        'spam_tag' => '[Spam-Verdacht]',
        'heading' => 'Neuer Widerruf eingegangen',
        'spam_warning' => 'Spam-Verdacht (Grund: :reason) — bitte prüfen. Der Eingang wurde dennoch gespeichert und dem Verbraucher bestätigt.',
        'spam_status' => 'Spam-Status',
        'spam_yes' => 'Verdacht',
        'spam_no' => 'unauffällig',
    ],

];
