<?php

declare(strict_types=1);

/*
| Operator panel UI strings — English (source of truth).
| Must be COMPLETE: APP_FALLBACK_LOCALE=de means any missing key falls back to
| German, leaking consumer-facing copy into the English operator panel.
*/

return [

    'settings' => [
        'navigation_group' => 'Settings',
        'localization' => [
            'navigation_label' => 'Localization',
            'title' => 'Localization',
            'available' => [
                'label' => 'Offered languages',
                'help' => 'Languages the consumer form offers in its switcher. The switcher is hidden when only one is offered.',
            ],
            'default' => [
                'label' => 'Default language',
                'help' => 'Applied when the consumer has not chosen a language.',
                'not_available' => 'The default language must be one of the offered languages.',
            ],
        ],
        'scope' => [
            'navigation_label' => 'Withdrawal scope',
            'title' => 'Withdrawal scope',
            'description' => 'Declares which contract types the form names. This selection only shapes the form wording — it never restricts what can be withdrawn. Consumers can always describe their case in free text.',
            'goods' => [
                'label' => 'Goods',
                'help' => 'The shop sells physical goods.',
            ],
            'services' => [
                'label' => 'Services',
                'help' => 'The shop offers services.',
            ],
            'digital' => [
                'label' => 'Digital content',
                'help' => 'The shop offers digital content, such as downloads or streaming.',
            ],
        ],
    ],

    'resource' => [
        'navigation_label' => 'Withdrawals',
        'model_label' => 'Withdrawal',
        'plural_model_label' => 'Withdrawals',
    ],

    'column' => [
        'received' => 'Received',
        'name' => 'Name',
        'order_number' => 'Order #',
        'no_spam' => 'No Spam',
        'handled' => 'Handled',
    ],

    'filter' => [
        'handled_status' => 'Handled status',
        'handled_only' => 'Handled only',
        'unhandled_only' => 'Unhandled only',
        'spam_status' => 'Spam status',
        'spam_only' => 'Spam only',
        'not_spam_only' => 'Not spam only',
        'date_range' => 'Date range',
        'date_from' => 'From',
        'date_until' => 'Until',
    ],

    'action' => [
        'mark_handled' => 'Mark handled',
        'unmark_handled' => 'Unmark handled',
    ],

    'infolist' => [
        'section' => [
            'submitter' => 'Submitter details',
            'statement' => 'Withdrawal statement',
            'triage' => 'Triage',
            'status' => 'Status & timestamps',
        ],
        'field' => [
            'name' => 'Name',
            'email' => 'Email',
            'order_number' => 'Order number',
            'locale' => 'Locale',
            'subject' => 'Subject',
            'spam_signal' => 'Spam signal',
            'spam_reason' => 'Spam reason',
            'handled_at' => 'Handled at',
            'received_at' => 'Received at',
            'last_updated' => 'Last updated',
        ],
        'spam' => [
            'yes' => 'Spam',
            'no' => 'Not spam',
        ],
        'not_handled' => 'Not handled',
    ],

];
