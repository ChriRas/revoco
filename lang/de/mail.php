<?php

declare(strict_types=1);

/*
| E-mail copy. Consumer-facing acknowledgment in the consumer's language;
| the merchant notification is operator-facing (app default locale).
| The acknowledgment confirms RECEIPT only (§ 356a Abs. 4) and deliberately carries
| no advertising (a design choice, not a statutory requirement of § 356a).
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
        // Prominent subject prefix for a spam-flagged case (prepended, not appended).
        'spam_prefix' => '⚠ SPAM-VERDACHT:',
        'heading' => 'Neuer Widerruf eingegangen',
        'spam_warning' => 'Spam-Verdacht (Grund: :reason) — bitte prüfen. Der Eingang wurde dennoch gespeichert und dem Verbraucher bestätigt.',
        'spam_status' => 'Spam-Status',
        'spam_yes' => 'Verdacht',
        'spam_no' => 'unauffällig',
        'mark_spam_link' => 'Als Spam markieren',
        'mark_spam_hint' => 'Der Link öffnet eine Bestätigungsseite und ist :days Tage gültig. Erst die Bestätigung ändert etwas.',
    ],

    'digest' => [
        'subject' => '{1} Spam-Zusammenfassung: 1 Eingang seit der letzten Zusammenfassung|[2,*] Spam-Zusammenfassung: :count Eingänge seit der letzten Zusammenfassung',
        'heading' => 'Spam-Zusammenfassung',
        'intro' => '{1} Seit der letzten Zusammenfassung ist 1 Widerruf als Spam eingestuft worden. Er wurde gespeichert, aber weder bestätigt noch einzeln gemeldet.|[2,*] Seit der letzten Zusammenfassung sind :count Widerrufe als Spam eingestuft worden. Sie wurden gespeichert, aber weder bestätigt noch einzeln gemeldet.',
        'spam_heading' => 'Als Spam eingestuft',
        'suspected_heading' => 'Verdächtig',
        'suspected_intro' => '{1} Seit der letzten Zusammenfassung ist 1 Widerruf als verdächtig eingestuft worden. Er liegt im Posteingang, die Eingangsbestätigung wurde verschickt.|[2,*] Seit der letzten Zusammenfassung sind :count Widerrufe als verdächtig eingestuft worden. Sie liegen im Posteingang, die Eingangsbestätigung wurde verschickt.',
        'reason' => 'Grund',
        'not_spam_link' => 'Kein Spam',
        'spam_link' => 'Als Spam markieren',
        'auto_sorted' => '{1} 1 Eingang wurde automatisch als Spam aussortiert (Honeypot + Bot-Signal) und wird ab dem :date gelöscht.|[2,*] :count Eingänge wurden automatisch als Spam aussortiert (Honeypot + Bot-Signal) und werden ab dem :date gelöscht.',
        'auto_sorted_link' => 'Im Spam-Reiter prüfen',
        'more' => '… und :count weitere',
        'panel_link' => 'Alle im Backend ansehen',
    ],

];
