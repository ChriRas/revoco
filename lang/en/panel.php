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
        'notification' => [
            'navigation_label' => 'Notifications',
            'title' => 'Notifications',
            'effective' => 'Notifications currently go to: :email',
            'effective_none' => 'No recipient is configured yet — no operator notifications are being sent.',
            'email' => [
                'label' => 'Recipient address',
                'help' => 'Address new withdrawals are reported to. It may differ from the sending address (e.g. sent via no-reply@, received at shop@). If left empty, the MERCHANT_NOTIFICATION_EMAIL environment variable and otherwise the imprint e-mail is used.',
            ],
            'spam_digest_time' => [
                'label' => 'Spam digest time',
                'help' => 'Every day at this time (HH:MM, application time zone) a summary goes to the recipient address: the spam and suspected withdrawals without your decision received since the last summary, plus the number sorted out automatically (at most 7 days back). Nothing is sent when there are none.',
            ],
            'test' => [
                'button' => 'Send test e-mail',
                'sent' => 'Test e-mail sent to :email.',
                'failed' => 'The test e-mail could not be sent.',
                'none' => 'No recipient configured — set an address first, or set the imprint e-mail.',
                'sample_subject' => 'Test notification (sample)',
            ],
        ],
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
        'network' => [
            'navigation_label' => 'Network signals',
            'title' => 'Network signals',
            'description' => 'The country and the network of a submission are derived from its IP address on this server. They only add to the spam score and never block a submission. The IP address itself is not stored.',
            'shipping_countries' => [
                'label' => 'Shipping countries',
                'help' => 'Signal only — never blocks. A submission from another country adds a small spam signal. Empty = signal off.',
            ],
            'status' => [
                'heading' => 'Data sources',
                'help' => 'Refreshed daily. Without data the network signals stay silent.',
                'last_refresh' => 'Last refresh',
                'entries' => 'Entries',
                'never' => 'never',
                'source' => [
                    'geo' => 'Country and network database',
                    'drop' => 'Listed IP networks',
                    'asn-drop' => 'Listed network operators',
                    'tor' => 'Tor exit nodes',
                ],
            ],
            'refresh' => [
                'button' => 'Refresh now',
                'queued' => 'Refresh queued — the queue worker fetches the data.',
            ],
            'attribution' => 'IP Geolocation by DB-IP',
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
            'imprint_address' => [
                'label' => 'Postal address',
                'help' => 'Full postal address (no P.O. box) as required by § 5 Abs. 1 Nr. 1 DDG. Enter per language when the country name or other details differ by locale.',
            ],
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

    'editor' => [
        'paste_html' => [
            'tool' => 'Paste HTML',
            'heading' => 'Paste HTML',
            'description' => 'Paste HTML here (e.g. a privacy policy delivered by a law firm). The content is sanitized and inserted into the editor as formatted text.',
            'placeholder' => '<h2>Heading</h2><p>Your HTML …</p>',
            'submit' => 'Insert',
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
        'spam_level' => 'Spam level',
        'handled' => 'Handled',
        'country' => 'Country',
    ],

    'filter' => [
        'handled_status' => 'Handled status',
        'handled_only' => 'Handled only',
        'unhandled_only' => 'Unhandled only',
        'spam_level' => 'Spam level',
        'date_range' => 'Date range',
        'date_from' => 'From',
        'date_until' => 'Until',
    ],

    'action' => [
        'mark_handled' => 'Mark handled',
        'handled_not_needed' => 'Spam – no handling needed',
        'unmark_handled' => 'Unmark handled',
        'mark_spam' => 'Mark as spam',
        'mark_spam_heading' => 'Mark as spam?',
        'mark_spam_description' => 'The withdrawal moves to the Spam tab and is deleted for good in :days days. You can undo this until then with "Not spam".',
        'is_not_spam' => 'Confirmed not spam',
        'not_spam' => 'Not spam',
        'not_spam_heading' => 'Mark as not spam?',
        'not_spam_description' => 'The withdrawal moves to the Inbox tab and is never deleted automatically. The acknowledgment goes to the submitter if it has not been sent yet.',
    ],

    'tab' => [
        'inbox' => 'Inbox',
        'spam' => 'Spam',
    ],

    'spam_level' => [
        'green' => 'Clean',
        'yellow' => 'Suspicious',
        'red' => 'Spam',
    ],

    'spam_verdict' => [
        'spam' => 'Confirmed spam',
        'not_spam' => 'Not spam',
        'spam_auto' => 'Spam (automatic)',
        'spam_operator' => 'Spam (your decision)',
        'not_spam_operator' => 'Not spam (your decision)',
    ],

    'spam_signal' => [
        'honeypot' => 'Hidden honeypot field filled',
        'throttle' => 'Too many submissions from one IP address',
        'render_token_invalid' => 'Form token missing or invalid',
        'fill_time_too_fast' => 'Form filled in implausibly fast',
        'user_agent_ancient' => 'Outdated or missing browser identifier',
        'content_gibberish' => 'Name or subject looks random',
        'name_without_space' => 'Name without a space',
        'field_lengths_equal' => 'Several fields of identical length',
        'country_outside_shipping' => 'Country outside the shipping countries',
        'drop_network' => 'IP address in a listed network',
        'asn_drop' => 'Listed network operator',
        'tor_exit' => 'Tor exit node',
        'learned_network' => 'Network with repeatedly confirmed spam',
        'abused_address' => 'Address known from confirmed spam',
    ],

    'setup' => [
        'warning' => 'Setup pending — the following pages are missing: :pages.',
        'link' => 'Configure legal pages',
        'page_imprint' => 'Imprint',
        'page_privacy' => 'Privacy Policy',
    ],

    'preview' => [
        'heading' => 'Preview',
        'empty' => 'No withdrawals in this tab.',
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
            'spam_level' => 'Spam level',
            'spam_reason' => 'Spam reason',
            'spam_verdict' => 'Your verdict',
            'spam_verdict_at' => 'Verdict at',
            'country' => 'Country',
            'network' => 'Network',
            'handled_at' => 'Handled at',
            'received_at' => 'Received at',
            'last_updated' => 'Last updated',
        ],
        'not_handled' => 'Not handled',
    ],

];
