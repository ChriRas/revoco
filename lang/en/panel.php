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
        'legal' => [
            'navigation_label' => 'Legal',
            'title' => 'Legal',
            'tab_privacy' => 'Privacy Policy',
            'tab_imprint' => 'Imprint',
            'privacy_content' => [
                'label' => 'Privacy policy',
                'help' => 'Your own privacy text per language. Revoco ships no ready-made legal text — as the controller, you are responsible for the content.',
            ],
            'privacy_link' => [
                'label' => 'External privacy-policy URL (override)',
                'help' => 'When set, the footer links here and the internal page redirects here. Leave empty to use the content above.',
            ],
            'fallback_order' => [
                'label' => 'Fallback language order',
                'help' => 'Languages tried, in order, when the requested language has no content.',
            ],
            'imprint_link' => [
                'label' => 'External imprint URL (override)',
                'help' => 'When set, the footer links here and the internal page redirects here. Leave empty to use the fields below.',
            ],
            'imprint_entity' => [
                'label' => 'Company details',
                'help' => 'Name, legal form, and authorized representative as required by § 5 Abs. 1 Nr. 1 DDG.',
            ],
            'imprint_contact' => [
                'label' => 'Contact',
                'help' => 'E-mail address and a second fast contact channel (§ 5 Abs. 1 Nr. 2 DDG, EuGH C-298/07).',
            ],
            'imprint_register' => [
                'label' => 'Commercial register',
                'help' => 'Register court and register number, if applicable (§ 5 Abs. 1 Nr. 4 DDG).',
            ],
            'imprint_tax' => [
                'label' => 'Tax information',
                'help' => 'VAT identification number (§ 27a UStG) and/or economic identification number (§ 139c AO), if held (§ 5 Abs. 1 Nr. 6 DDG).',
            ],
            'imprint_professional' => [
                'label' => 'Professional regulations (optional)',
                'help' => 'Only for regulated professions and activities requiring official authorization (§ 5 Abs. 1 Nr. 3 and 5 DDG).',
            ],
            'imprint_addendum' => [
                'label' => 'Free-form addendum',
                'help' => 'Per-language free text for any additional mandatory notices or operator-specific information (e.g. a dispute-resolution notice on counsel\'s advice).',
            ],
            'imprint_name' => ['label' => 'Name / company name'],
            'imprint_legal_form' => ['label' => 'Legal form'],
            'imprint_represented_by' => ['label' => 'Represented by'],
            'imprint_address' => ['label' => 'Postal address (no P.O. box)'],
            'imprint_email' => ['label' => 'E-mail address'],
            'imprint_phone' => ['label' => 'Phone number'],
            'imprint_contact_note' => ['label' => 'Additional contact note'],
            'imprint_register_court' => ['label' => 'Register court'],
            'imprint_register_number' => ['label' => 'Register number'],
            'imprint_vat_id' => ['label' => 'VAT identification number'],
            'imprint_business_id' => ['label' => 'Economic identification number'],
            'imprint_supervisory_authority' => ['label' => 'Supervisory authority'],
            'imprint_chamber' => ['label' => 'Chamber'],
            'imprint_job_title' => ['label' => 'Job title and country of grant'],
            'imprint_professional_rules' => ['label' => 'Professional rules and how to access them'],
            'imprint_liquidation_note' => ['label' => 'Liquidation / insolvency statement (§ 5 Nr. 7 DDG)'],
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
