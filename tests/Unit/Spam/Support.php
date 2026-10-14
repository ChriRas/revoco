<?php

declare(strict_types=1);

use App\Spam\SubmissionContext;
use Carbon\CarbonImmutable;

/**
 * A clean submission context; override single fields per test.
 *
 * @param  array<string, mixed>  $overrides
 */
function spamContext(array $overrides = []): SubmissionContext
{
    return new SubmissionContext(...array_merge([
        'name' => 'Max Mustermann',
        'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'honeypotFilled' => false,
        'renderToken' => null,
        'userAgent' => CLEAN_BROWSER_USER_AGENT,
        'ip' => '203.0.113.7',
        'submittedAt' => CarbonImmutable::now(),
    ], $overrides));
}
