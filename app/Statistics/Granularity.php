<?php

declare(strict_types=1);

namespace App\Statistics;

use Illuminate\Support\Carbon;

/** The width of one bar in the history chart. */
enum Granularity: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';

    /**
     * SQLite expression that maps the counter's `day` column to this granularity's bucket key.
     *
     * @return literal-string
     */
    public function bucketSql(): string
    {
        return match ($this) {
            self::Day => 'day',
            self::Month => "strftime('%Y-%m', day)",
            self::Year => "strftime('%Y', day)",
        };
    }

    /** The bucket key of a date, matching `bucketSql()`. */
    public function key(Carbon $date): string
    {
        return $date->format(match ($this) {
            self::Day => 'Y-m-d',
            self::Month => 'Y-m',
            self::Year => 'Y',
        });
    }

    /** The axis label of the bucket that starts at the given date. */
    public function label(Carbon $date): string
    {
        return match ($this) {
            self::Day => $date->format(__('panel.statistics.chart.day_format')),
            self::Month => $date->translatedFormat('M'),
            self::Year => $date->format('Y'),
        };
    }

    /** The first day of the next bucket. */
    public function next(Carbon $date): Carbon
    {
        return match ($this) {
            self::Day => $date->copy()->addDay(),
            self::Month => $date->copy()->addMonthNoOverflow()->startOfMonth(),
            self::Year => $date->copy()->addYear()->startOfYear(),
        };
    }
}
