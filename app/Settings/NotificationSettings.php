<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * DB-backed recipient for the operator withdrawal notification, managed by the
 * operator on the Filament "Notifications" settings page. Deliberately decoupled
 * from the mail *from* address — a no-reply may send while the alerts go to e.g.
 * shop@… . Empty by default: the recipient then falls back to the
 * MERCHANT_NOTIFICATION_EMAIL env override and finally the § 5 DDG imprint e-mail
 * (see App\Support\NotificationRecipient). SMTP transport stays in MAIL_* env —
 * secrets never live in this settings table.
 */
final class NotificationSettings extends Settings
{
    /** Operator address new withdrawals are reported to; null → fall back (see resolver). */
    public ?string $notification_email = null;

    public static function group(): string
    {
        return 'notification';
    }
}
