<?php

declare(strict_types=1);

/*
| User-facing copy for the withdrawal form (English).
| End-user UI text is the one committed-artifact exception to the English-only
| code rule. Mirrors lang/de/wf.php key-for-key: every key the Blade view
| references must exist here, or the literal "wf.*" identifier renders.
*/

return [

    'title' => 'Withdrawal Form',
    'subtitle' => 'Please complete the fields below.',

    'field' => [
        'name' => [
            'label' => 'Your first and last name',
            'error' => 'Please enter your first and last name.',
        ],
        'email' => [
            'label' => 'E-mail address',
            'error' => 'Please enter your e-mail address.',
            'invalid' => 'Please enter a valid e-mail address.',
        ],
        'order' => [
            'label' => 'Order number / contract number',
        ],
        'subject' => [
            'label' => 'Affected goods, digital content, or service',
            'error' => 'Please state which goods, digital content, or service you wish to withdraw from.',
        ],
    ],

    'badge' => [
        'required' => 'Required',
        'optional' => 'optional',
    ],

    'language' => [
        'label' => 'Choose language',
        // Autonyms — each language in its own name, identical across locales.
        'names' => [
            'de' => 'Deutsch',
            'en' => 'English',
        ],
    ],

    'submit' => 'Confirm withdrawal',
    'hint' => 'Fields marked “Required” are mandatory.',

    'honeypot' => [
        'label' => 'Please leave this field empty.',
    ],

    'success' => [
        'title' => 'Receipt of your withdrawal confirmed',
        'body' => 'Your withdrawal declaration has reached us and is being reviewed.',
        'note' => 'You can close this window now.',
    ],

    'footer' => [
        'imprint' => 'Imprint',
        'privacy' => 'Privacy Policy',
        'source' => 'Source code',
    ],

    'legal' => [
        'privacy' => [
            'title' => 'Privacy Policy',
        ],
        // Neutral hint shown when the operator has not configured the page yet —
        // deliberately NOT legal text (no Lorem Ipsum, nothing that could read as valid).
        'placeholder' => 'This page has not been set up yet.',
        // Sticky control on the (long) legal pages — returns to the withdrawal form.
        'back' => 'Back to the form',
    ],

];
