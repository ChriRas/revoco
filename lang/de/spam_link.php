<?php

declare(strict_types=1);

/*
| Operator-facing copy for the signed mail-link pages. German only on purpose,
| like the rest of the operator mail copy; rendered in the app default locale.
| The pages carry no name, e-mail or subject.
*/

return [

    'entry' => 'Vorgang Nr. :id',
    'status' => 'Aktueller Status',
    'closed' => 'Dieser Link wurde bereits verwendet oder gilt nicht mehr. Den aktuellen Status sehen Sie oben.',

    'applied' => [
        'spam' => 'Erledigt: als Spam markiert. Der Widerruf wird nach :days Tagen endgültig gelöscht; bis dahin können Sie das im Backend rückgängig machen.',
        'not-spam' => 'Erledigt: als „Kein Spam“ markiert. Die Eingangsbestätigung geht an den Einreicher, falls sie noch nicht verschickt wurde.',
    ],

    'confirm' => [
        'title' => [
            'spam' => 'Als Spam markieren?',
            'not-spam' => 'Als „Kein Spam“ markieren?',
        ],
        'text' => [
            'spam' => 'Der Widerruf wird als Spam bestätigt und nach :days Tagen endgültig gelöscht. Bis dahin können Sie das im Backend rückgängig machen.',
            'not-spam' => 'Der Widerruf wird als „Kein Spam“ bestätigt und nie automatisch gelöscht. Die Eingangsbestätigung geht an den Einreicher, falls sie noch nicht verschickt wurde.',
        ],
        'button' => [
            'spam' => 'Als Spam markieren',
            'not-spam' => 'Kein Spam',
        ],
    ],

    'invalid' => [
        'title' => 'Link ungültig',
        'text' => 'Dieser Link ist ungültig oder abgelaufen.',
    ],

    'gone' => [
        'title' => 'Nicht gefunden',
        'text' => 'Dieser Vorgang existiert nicht mehr.',
    ],

];
