<?php

declare(strict_types=1);

namespace App\Spam;

use App\Settings\NotificationSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Registers the daily `revoco:spam-digest` run at the time the operator set on the
 * notifications page. The setting lives in the database, so reading it must never break
 * artisan boot: on a fresh, unmigrated database (or an unusable value) the default applies.
 */
final class SpamDigestSchedule
{
    public const string DEFAULT_TIME = '07:00';

    public static function register(Schedule $schedule): void
    {
        $schedule->command('revoco:spam-digest')
            ->dailyAt(self::time())
            ->timezone(Config::string('app.timezone'));
    }

    public static function time(): string
    {
        try {
            $time = app(NotificationSettings::class)->spam_digest_time;
        } catch (Throwable) {
            return self::DEFAULT_TIME;
        }

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : self::DEFAULT_TIME;
    }
}
