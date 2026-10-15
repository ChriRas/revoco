<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Models\Withdrawal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

/** The create-table migration, which also holds the backfill. */
function countsMigration(): Migration
{
    $migration = require database_path('migrations/2026_10_15_100000_create_withdrawal_daily_counts_table.php');
    assert($migration instanceof Migration);

    return $migration;
}

/** Runs the deploy: drops the table and lets the migration create and backfill it again. */
function rerunBackfill(): void
{
    $migration = countsMigration();
    $migration->down();
    $migration->up();
}

/**
 * A raw row, bypassing the model so the observer stays silent.
 *
 * @param  array<string, mixed>  $attributes
 */
function rawWithdrawal(string $createdAt, string $level, array $attributes = []): void
{
    DB::table('withdrawals')->insert($attributes + [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'subject' => 'Schlauchboot Modell X',
        'locale' => 'de',
        'spam_level' => $level,
        'spam_reason' => null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

/**
 * The counter table as a sorted, comparable list. Live recording keeps a bucket that fell to 0, the
 * backfill never creates one, so empty buckets are left out.
 *
 * @return list<string>
 */
function counterTable(): array
{
    return DB::table('withdrawal_daily_counts')
        ->where('count', '!=', 0)
        ->orderBy('day')->orderBy('level')->orderBy('outcome')->orderBy('signal')
        ->get()
        ->map(fn (object $row): string => "{$row->day}|{$row->level}|{$row->outcome}|{$row->signal}={$row->count}")
        ->all();
}

it('counts every state across two days, with multi-signal reasons and a legacy null reason', function () {
    $day1 = '2026-10-10 09:30:00';
    $day2 = '2026-10-11 23:59:59';

    rawWithdrawal($day1, 'green');
    rawWithdrawal($day1, 'green', ['spam_reason' => null]);
    rawWithdrawal($day1, 'yellow', ['spam_reason' => 'throttle,name_without_space']);
    rawWithdrawal($day1, 'red', ['spam_reason' => 'honeypot']);
    rawWithdrawal($day2, 'red', ['spam_reason' => 'honeypot,user_agent_ancient', 'spam_verdict' => 'spam', 'spam_verdict_source' => 'operator']);
    rawWithdrawal($day2, 'red', ['spam_reason' => 'honeypot,user_agent_ancient', 'spam_verdict' => 'spam', 'spam_verdict_source' => 'auto']);
    rawWithdrawal($day2, 'yellow', ['spam_reason' => 'throttle', 'spam_verdict' => 'not_spam', 'spam_verdict_source' => 'operator']);
    rawWithdrawal($day2, 'green', ['spam_verdict' => 'spam', 'spam_verdict_source' => null]);

    rerunBackfill();

    expect(counterTable())->toBe([
        '2026-10-10|green|none|=2',
        '2026-10-10|red|none|=1',
        '2026-10-10|red|none|honeypot=1',
        '2026-10-10|yellow|none|=1',
        '2026-10-10|yellow|none|name_without_space=1',
        '2026-10-10|yellow|none|throttle=1',
        '2026-10-11|green|spam_operator|=1',
        '2026-10-11|red|spam_auto|=1',
        '2026-10-11|red|spam_auto|honeypot=1',
        '2026-10-11|red|spam_auto|user_agent_ancient=1',
        '2026-10-11|red|spam_operator|=1',
        '2026-10-11|red|spam_operator|honeypot=1',
        '2026-10-11|red|spam_operator|user_agent_ancient=1',
        '2026-10-11|yellow|not_spam|=1',
        '2026-10-11|yellow|not_spam|throttle=1',
    ]);
});

it('counts a duplicate or blank key in a reason once', function () {
    rawWithdrawal('2026-10-10 10:00:00', 'red', ['spam_reason' => 'honeypot, honeypot,,throttle']);

    rerunBackfill();

    expect(counterTable())->toBe([
        '2026-10-10|red|none|=1',
        '2026-10-10|red|none|honeypot=1',
        '2026-10-10|red|none|throttle=1',
    ]);
});

it('backfills an empty withdrawals table to an empty counter table', function () {
    rerunBackfill();

    expect(counterTable())->toBe([]);
});

it('produces the same table as live recording for the same states', function () {
    Withdrawal::factory()->count(2)->create(['created_at' => '2026-10-10 09:00:00']);
    Withdrawal::factory()->yellow()->create(['created_at' => '2026-10-10 12:00:00']);
    Withdrawal::factory()->spam()->create(['created_at' => '2026-10-11 08:00:00']);
    Withdrawal::factory()->autoSorted()->create(['created_at' => '2026-10-11 08:30:00']);
    Withdrawal::factory()->confirmedSpam()->create(['created_at' => '2026-10-11 09:00:00']);
    Withdrawal::factory()->yellow()->notSpam()->create(['created_at' => '2026-10-11 10:00:00']);
    Withdrawal::factory()->spam()->notSpam()->create(['created_at' => '2026-10-12 10:00:00']);

    $green = Withdrawal::factory()->create(['created_at' => '2026-10-12 11:00:00']);
    app(MarkWithdrawalAsSpam::class)->handle($green);

    $released = Withdrawal::factory()->autoSorted()->create(['created_at' => '2026-10-12 12:00:00']);
    app(MarkWithdrawalAsNotSpam::class)->handle($released);

    $live = counterTable();

    rerunBackfill();

    expect(counterTable())->toBe($live)
        ->and($live)->not->toBe([]);
});
