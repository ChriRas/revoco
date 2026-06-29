<?php

declare(strict_types=1);

/*
| E-mail copy. Consumer-facing acknowledgment in the consumer's language;
| the merchant notification is operator-facing (app default locale).
| The acknowledgment confirms RECEIPT only and carries NO advertising (§ 356a Abs. 4).
*/

return [

    'uhr' => 'Uhr',
    // Per-locale receipt date format (PHP date()). The consumer acknowledgment
    // follows the consumer locale; the merchant notification stays on this
    // default locale, so its rendered date is always this German format.
    'datetime_format' => 'd.m.Y, H:i',
    // Timezone label. The abbreviation is derived from the timestamp via PHP's
    // `T` token (CET in winter, CEST in summer) and mapped to the German
    // MEZ/MESZ; `timezone_format` adds the surrounding parentheses.
    'timezone_format' => '(:tz)',
    'tz' => ['CET' => 'MEZ', 'CEST' => 'MESZ'],

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
