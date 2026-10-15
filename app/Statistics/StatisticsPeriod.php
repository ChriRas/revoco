<?php

declare(strict_types=1);

namespace App\Statistics;

use Illuminate\Support\Carbon;

/**
 * The calendar period the statistics page shows, in the application time zone: today, the
 * current month, the current year, or everything counted so far.
 */
enum StatisticsPeriod: string
{
    case Today = 'today';
    case Month = 'month';
    case Year = 'year';
    case All = 'all';

    public const self DEFAULT = self::Month;

    public function label(): string
    {
        return __('panel.statistics.period.'.$this->value);
    }

    /** The period of a (possibly missing or tampered) filter value; the default when unknown. */
    public static function fromFilter(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::DEFAULT) : self::DEFAULT;
    }

    /** First day of the period; null when open to the past (all time). */
    public function firstDay(): ?Carbon
    {
        return match ($this) {
            self::Today => now()->startOfDay(),
            self::Month => now()->startOfMonth(),
            self::Year => now()->startOfYear(),
            self::All => null,
        };
    }

    /** Last day of the period; null when open to the future (all time). */
    public function lastDay(): ?Carbon
    {
        return match ($this) {
            self::Today => now()->startOfDay(),
            self::Month => now()->endOfMonth()->startOfDay(),
            self::Year => now()->endOfYear()->startOfDay(),
            self::All => null,
        };
    }

    /** The period the history chart covers: today has no finer grain than days, so it shows its month. */
    public function chartPeriod(): self
    {
        return $this === self::Today ? self::Month : $this;
    }

    public function granularity(): Granularity
    {
        return match ($this->chartPeriod()) {
            self::Month => Granularity::Day,
            self::Year => Granularity::Month,
            default => Granularity::Year,
        };
    }
}
