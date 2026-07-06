<?php

declare(strict_types=1);

namespace App\Support;

use App\Settings\LegalSettings;
use App\Settings\NotificationSettings;

/**
 * Resolves the operator notification recipient with a precedence that serves both
 * deployment personas without double entry:
 *
 *   1. the explicit panel setting (NotificationSettings::$notification_email),
 *   2. else the MERCHANT_NOTIFICATION_EMAIL env override (config revoco.merchant_email),
 *   3. else the already-required § 5 DDG imprint e-mail (LegalSettings::$imprint_email),
 *   4. else null — nothing is sent (the panel surfaces this).
 *
 * Explicit operator intent (panel, then env) beats the imprint default. An empty
 * or whitespace-only value at any level is treated as unset.
 */
final class NotificationRecipient
{
    public static function resolve(): ?string
    {
        $panel = app(NotificationSettings::class)->notification_email;
        if (is_string($panel) && trim($panel) !== '') {
            return trim($panel);
        }

        $env = config('revoco.merchant_email');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        $imprint = app(LegalSettings::class)->imprint_email;
        if (is_string($imprint) && trim($imprint) !== '') {
            return trim($imprint);
        }

        return null;
    }
}
