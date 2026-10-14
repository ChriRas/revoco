<?php

declare(strict_types=1);

namespace App\Spam;

use App\Enums\SpamLinkAction;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Builds and checks the signed operator links in the merchant mail and the digest.
 *
 * The signature covers path and query only (`signed:relative`), so a link stays valid
 * whatever scheme and host the app sees behind a TLS-terminating proxy; the origin in
 * the mail is the public APP_URL. A link is single-use through its `state`: it applies
 * only while the row's verdict still equals the one at issue.
 */
final class SpamLinks
{
    /** Days a link stays valid; matches the purge horizon. */
    public const int TTL_DAYS = 7;

    /** Session flash key carrying the action a link just applied, for the page after the redirect. */
    public const string APPLIED_FLASH = 'spam_link_applied';

    public static function url(Withdrawal $withdrawal, SpamLinkAction $action): string
    {
        return self::origin().URL::temporarySignedRoute(
            'spam-link.show',
            now()->addDays(self::TTL_DAYS),
            ['withdrawal' => $withdrawal->id, 'action' => $action->value, 'state' => self::state($withdrawal)],
            absolute: false,
        );
    }

    /** The row's operator verdict, as carried in a link's `state` parameter. */
    public static function state(Withdrawal $withdrawal): string
    {
        return $withdrawal->spam_verdict->value ?? 'none';
    }

    /** The public origin (APP_URL) the browser must stay on. */
    public static function origin(): string
    {
        return rtrim(Config::string('app.url'), '/');
    }
}
