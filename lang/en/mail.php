<?php

declare(strict_types=1);

/*
| E-mail copy (English) — consumer acknowledgment only.
| The acknowledgment is rendered in the consumer's chosen locale; the merchant
| notification is operator-facing and pinned to the app default locale, so its
| `notification` subtree lives only in lang/de/mail.php and is deliberately
| absent here. The acknowledgment confirms RECEIPT only and carries NO
| advertising (§ 356a Abs. 4).
*/

return [

    // English 24h time takes no "o'clock" suffix; the timezone abbreviation
    // conveys the zone. Empty by design (HTML collapses the gap).
    'uhr' => '',
    // US-style receipt date for the English acknowledgment ("Jun 27, 2026, 14:30");
    // the German default uses 'd.m.Y, H:i'. Plain date() suffices — the month
    // abbreviation is already English, so no Carbon translatedFormat() is needed.
    'datetime_format' => 'M j, Y, H:i',
    // Timezone label: the neutral abbreviation from PHP's `T` token (CET/CEST),
    // appended without parentheses ("14:30 CEST").
    'timezone_format' => ':tz',
    'tz' => ['CET' => 'CET', 'CEST' => 'CEST'],

    'field' => [
        'name' => 'Name',
        'email' => 'E-mail address',
        'order' => 'Order / contract number',
        'subject' => 'Affected goods / digital content / service',
        'datetime' => 'Received on',
    ],

    'ack' => [
        'subject' => 'Confirmation of receipt of your withdrawal',
        'heading' => 'Receipt of your withdrawal confirmed',
        'greeting' => 'Dear :name,',
        'intro' => 'we hereby confirm receipt of the withdrawal declaration below. This e-mail documents the receipt of your declaration only.',
        'declaration_heading' => 'Your details',
        'outro' => 'Your request will now be processed. If you have any questions, simply reply to this e-mail.',
    ],

];
