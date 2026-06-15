<?php

declare(strict_types=1);

/*
| ntfy push copy (operator-facing). DATA-MINIMAL — never contains PII
| (no name / e-mail / order / subject), only a bare arrival notice.
*/

return [

    'title' => 'Neuer Widerruf',
    'body' => 'Ein neuer Widerruf ist eingegangen.',
    'body_spam' => 'Ein neuer Widerruf ist eingegangen (Spam-Verdacht).',

];
