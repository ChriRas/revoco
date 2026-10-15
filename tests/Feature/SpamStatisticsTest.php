<?php

declare(strict_types=1);

use App\Enums\SpamLevel;
use App\Models\WithdrawalDailyCount;
use App\Statistics\Outcome;
use App\Statistics\SpamStatistics;
use App\Statistics\StatisticsPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(Carbon\Carbon::parse('2026-10-15 12:00', 'Europe/Berlin'));
    app()->setLocale('de');
});

/** Seeds one counter row the way slice-033's recorder stores it. */
function seedCount(string $day, string $level, string $outcome, string $signal, int $count): void
{
    WithdrawalDailyCount::query()->create([
        'day' => $day,
        'level' => $level,
        'outcome' => $outcome,
        'signal' => $signal,
        'count' => $count,
    ]);
}

/** A varied counter set: today, earlier this month, earlier this year and a previous year. */
function seedStatisticsFixture(): void
{
    // Today: 3 clean, 2 suspicious, 1 unjudged red, 4 auto-sorted (all honeypot, 2 of them also throttle).
    seedCount('2026-10-15', 'green', 'none', '', 3);
    seedCount('2026-10-15', 'yellow', 'none', '', 2);
    seedCount('2026-10-15', 'yellow', 'none', 'content_gibberish', 2);
    seedCount('2026-10-15', 'red', 'none', '', 1);
    seedCount('2026-10-15', 'red', 'none', 'honeypot', 1);
    seedCount('2026-10-15', 'red', 'spam_auto', '', 4);
    seedCount('2026-10-15', 'red', 'spam_auto', 'honeypot', 4);
    seedCount('2026-10-15', 'red', 'spam_auto', 'throttle', 2);

    // Earlier this month: a false alarm, a missed green and a missed yellow.
    seedCount('2026-10-03', 'red', 'not_spam', '', 1);
    seedCount('2026-10-03', 'red', 'not_spam', 'honeypot', 1);
    seedCount('2026-10-03', 'green', 'spam_operator', '', 1);
    seedCount('2026-10-03', 'green', 'spam_operator', 'name_without_space', 1);
    seedCount('2026-10-03', 'yellow', 'spam_operator', '', 1);

    // Earlier this year, and a previous year.
    seedCount('2026-03-10', 'green', 'none', '', 5);
    seedCount('2025-05-20', 'green', 'none', '', 7);
    seedCount('2025-05-20', 'red', 'spam_auto', '', 2);
    seedCount('2025-05-20', 'red', 'spam_auto', 'honeypot', 2);
}

it('reads the totals of today from the total rows only', function () {
    seedStatisticsFixture();

    expect(app(SpamStatistics::class)->totals(StatisticsPeriod::Today))->toBe([
        'genuine' => 3,
        'suspected' => 2,
        'spam' => 5,
        'auto_sorted' => 4,
        'false_alarms' => 0,
        'missed' => 0,
    ]);
});

it('reads the totals of the current calendar month', function () {
    seedStatisticsFixture();

    expect(app(SpamStatistics::class)->totals(StatisticsPeriod::Month))->toBe([
        'genuine' => 4,
        'suspected' => 2,
        'spam' => 7,
        'auto_sorted' => 4,
        'false_alarms' => 1,
        'missed' => 2,
    ]);
});

it('reads the totals of the current year and of all time', function () {
    seedStatisticsFixture();

    expect(app(SpamStatistics::class)->totals(StatisticsPeriod::Year))->toMatchArray(['genuine' => 9, 'suspected' => 2, 'spam' => 7])
        ->and(app(SpamStatistics::class)->totals(StatisticsPeriod::All))->toMatchArray(['genuine' => 16, 'suspected' => 2, 'spam' => 9, 'auto_sorted' => 6]);
});

it('includes the first and last day of the period and nothing outside it', function () {
    seedCount('2026-09-30', 'green', 'none', '', 100);
    seedCount('2026-10-01', 'green', 'none', '', 1);
    seedCount('2026-10-31', 'green', 'none', '', 2);
    seedCount('2026-11-01', 'green', 'none', '', 100);
    seedCount('2025-12-31', 'yellow', 'none', '', 100);
    seedCount('2026-01-01', 'yellow', 'none', '', 1);
    seedCount('2026-12-31', 'yellow', 'none', '', 2);
    seedCount('2027-01-01', 'yellow', 'none', '', 100);

    $statistics = app(SpamStatistics::class);

    expect($statistics->totals(StatisticsPeriod::Month)['genuine'])->toBe(3)
        ->and($statistics->totals(StatisticsPeriod::Today)['genuine'])->toBe(0)
        ->and($statistics->totals(StatisticsPeriod::Year)['suspected'])->toBe(3);
});

it('is empty without counters', function () {
    $statistics = app(SpamStatistics::class);

    expect($statistics->totals(StatisticsPeriod::All))->toBe(['genuine' => 0, 'suspected' => 0, 'spam' => 0, 'auto_sorted' => 0, 'false_alarms' => 0, 'missed' => 0])
        ->and($statistics->signals(StatisticsPeriod::All))->toBe([])
        ->and(array_sum(array_column($statistics->history(StatisticsPeriod::Month), 'spam')))->toBe(0);
});

it('puts every stored state into the category and flags the mapping gives it', function (SpamLevel $level, Outcome $outcome) {
    seedCount('2026-10-15', $level->value, $outcome->value, '', 1);

    $totals = app(SpamStatistics::class)->totals(StatisticsPeriod::Today);

    expect($totals)->toBe([
        'genuine' => (int) ($outcome->category($level)->value === 'genuine'),
        'suspected' => (int) ($outcome->category($level)->value === 'suspected'),
        'spam' => (int) ($outcome->category($level)->value === 'spam'),
        'auto_sorted' => (int) $outcome->isAutoSorted(),
        'false_alarms' => (int) $outcome->isFalseAlarm($level),
        'missed' => (int) $outcome->isMissed($level),
    ]);
})->with(function (): Generator {
    foreach (SpamLevel::cases() as $level) {
        foreach (Outcome::cases() as $outcome) {
            yield "{$level->value} / {$outcome->value}" => [$level, $outcome];
        }
    }
});

it('lands the named states in the documented categories', function () {
    seedCount('2026-10-15', 'red', 'not_spam', '', 1);
    seedCount('2026-10-15', 'green', 'spam_operator', '', 1);
    seedCount('2026-10-15', 'red', 'none', '', 1);

    expect(app(SpamStatistics::class)->totals(StatisticsPeriod::Today))
        ->toBe(['genuine' => 1, 'suspected' => 0, 'spam' => 2, 'auto_sorted' => 0, 'false_alarms' => 1, 'missed' => 1]);
});

it('draws one bar per day of the month, empty days included, for today and month', function () {
    seedStatisticsFixture();

    foreach ([StatisticsPeriod::Month, StatisticsPeriod::Today] as $period) {
        $bars = app(SpamStatistics::class)->history($period);

        expect($bars)->toHaveCount(31)
            ->and($bars[0])->toBe(['key' => '2026-10-01', 'label' => '1.10.', 'genuine' => 0, 'suspected' => 0, 'spam' => 0])
            ->and($bars[2])->toBe(['key' => '2026-10-03', 'label' => '3.10.', 'genuine' => 1, 'suspected' => 0, 'spam' => 2])
            ->and($bars[14])->toBe(['key' => '2026-10-15', 'label' => '15.10.', 'genuine' => 3, 'suspected' => 2, 'spam' => 5])
            ->and($bars[30]['key'])->toBe('2026-10-31');
    }
});

it('labels the day bars in the panel locale', function () {
    app()->setLocale('en');

    expect(app(SpamStatistics::class)->history(StatisticsPeriod::Month)[14]['label'])->toBe('10/15');
});

it('draws one bar per month of the year, empty months included', function () {
    seedStatisticsFixture();

    $bars = app(SpamStatistics::class)->history(StatisticsPeriod::Year);

    expect($bars)->toHaveCount(12)
        ->and(array_column($bars, 'key'))->toBe(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12'])
        ->and($bars[2])->toMatchArray(['genuine' => 5, 'suspected' => 0, 'spam' => 0])
        ->and($bars[3])->toMatchArray(['genuine' => 0, 'suspected' => 0, 'spam' => 0])
        ->and($bars[9])->toMatchArray(['genuine' => 4, 'suspected' => 2, 'spam' => 7]);
});

it('draws one bar per year since the first counter day for all time', function () {
    seedStatisticsFixture();
    seedCount('2024-02-01', 'green', 'none', '', 1);

    $bars = app(SpamStatistics::class)->history(StatisticsPeriod::All);

    expect(array_column($bars, 'key'))->toBe(['2024', '2025', '2026'])
        ->and($bars[0])->toMatchArray(['genuine' => 1, 'spam' => 0])
        ->and($bars[1])->toMatchArray(['genuine' => 7, 'spam' => 2])
        ->and($bars[2])->toMatchArray(['genuine' => 9, 'suspected' => 2, 'spam' => 7]);
});

it('shows a single bar for all time without counters', function () {
    expect(array_column(app(SpamStatistics::class)->history(StatisticsPeriod::All), 'key'))->toBe(['2026']);
});

it('counts a declaration once in the tiles and once per signal in the signal rows', function () {
    seedStatisticsFixture();

    $statistics = app(SpamStatistics::class);
    $signals = collect($statistics->signals(StatisticsPeriod::Today))->keyBy('signal');

    // 4 auto-sorted + 1 unjudged red declarations: 5 spam in the tiles, but honeypot and throttle both fired on some.
    expect($statistics->totals(StatisticsPeriod::Today)['spam'])->toBe(5)
        ->and($signals['honeypot'])->toBe(['signal' => 'honeypot', 'genuine' => 0, 'suspected' => 0, 'spam' => 5, 'total' => 5])
        ->and($signals['throttle'])->toBe(['signal' => 'throttle', 'genuine' => 0, 'suspected' => 0, 'spam' => 2, 'total' => 2])
        ->and($signals['content_gibberish'])->toBe(['signal' => 'content_gibberish', 'genuine' => 0, 'suspected' => 2, 'spam' => 0, 'total' => 2]);
});

it('splits the signal counts by category and sorts them by total, busiest first', function () {
    seedStatisticsFixture();

    $signals = app(SpamStatistics::class)->signals(StatisticsPeriod::Month);

    expect(array_column($signals, 'signal'))->toBe(['honeypot', 'content_gibberish', 'throttle', 'name_without_space'])
        ->and($signals[0])->toBe(['signal' => 'honeypot', 'genuine' => 1, 'suspected' => 0, 'spam' => 5, 'total' => 6])
        ->and($signals[3])->toBe(['signal' => 'name_without_space', 'genuine' => 0, 'suspected' => 0, 'spam' => 1, 'total' => 1]);
});

it('does not list a signal whose period counts are all zero', function () {
    seedCount('2026-10-15', 'red', 'none', 'honeypot', 0);

    expect(app(SpamStatistics::class)->signals(StatisticsPeriod::Month))->toBe([]);
});

it('falls back to the default period for a missing or unknown filter value', function () {
    expect(StatisticsPeriod::fromFilter(null))->toBe(StatisticsPeriod::Month)
        ->and(StatisticsPeriod::fromFilter('decade'))->toBe(StatisticsPeriod::Month)
        ->and(StatisticsPeriod::fromFilter(['year']))->toBe(StatisticsPeriod::Month)
        ->and(StatisticsPeriod::fromFilter('year'))->toBe(StatisticsPeriod::Year);
});
