<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

/** The count of one bucket; 0 when it has never been written. */
function bucket(string $day, string $level, string $outcome, string $signal = ''): int
{
    return (int) DB::table('withdrawal_daily_counts')
        ->where(['day' => $day, 'level' => $level, 'outcome' => $outcome, 'signal' => $signal])
        ->value('count');
}

/**
 * Every counter row, in a stable order.
 *
 * @return list<array<string, mixed>>
 */
function allBuckets(): array
{
    return DB::table('withdrawal_daily_counts')
        ->orderBy('day')->orderBy('level')->orderBy('outcome')->orderBy('signal')
        ->get()
        ->map(fn (object $row): array => (array) $row)
        ->all();
}

/**
 * @return array<string, string>
 */
function countedPayload(array $extra = []): array
{
    return $extra + [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
    ];
}

it('counts a clean submit in its total bucket', function () {
    submitCleanWithdrawal(countedPayload())->assertRedirect(route('withdrawal.success'));

    expect(bucket(today()->toDateString(), 'green', 'none'))->toBe(1)
        ->and(DB::table('withdrawal_daily_counts')->count())->toBe(1);
});

it('counts an auto-sorted submit once in the total and once per fired signal', function () {
    submitCleanWithdrawal(countedPayload(['website' => 'https://spam.example']), ['HTTP_USER_AGENT' => ANCIENT_CHROME_USER_AGENT])
        ->assertRedirect(route('withdrawal.success'));

    $day = today()->toDateString();

    expect(bucket($day, 'red', 'spam_auto'))->toBe(1)
        ->and(bucket($day, 'red', 'spam_auto', 'honeypot'))->toBe(1)
        ->and(bucket($day, 'red', 'spam_auto', 'user_agent_ancient'))->toBe(1)
        ->and(DB::table('withdrawal_daily_counts')->count())->toBe(3);
});

it('moves every bucket of an auto-sorted row to not_spam when it is released', function () {
    submitCleanWithdrawal(countedPayload(['website' => 'https://spam.example']), ['HTTP_USER_AGENT' => ANCIENT_CHROME_USER_AGENT]);
    $day = today()->toDateString();

    app(MarkWithdrawalAsNotSpam::class)->handle(Withdrawal::sole());

    foreach (['', 'honeypot', 'user_agent_ancient'] as $signal) {
        expect(bucket($day, 'red', 'spam_auto', $signal))->toBe(0)
            ->and(bucket($day, 'red', 'not_spam', $signal))->toBe(1);
    }
});

it('moves a green row to spam_operator when the operator marks it as spam', function () {
    $row = Withdrawal::factory()->create();
    $day = $row->created_at?->toDateString() ?? '';

    app(MarkWithdrawalAsSpam::class)->handle($row);

    expect(bucket($day, 'green', 'none'))->toBe(0)
        ->and(bucket($day, 'green', 'spam_operator'))->toBe(1);
});

it('counts the verdict on the receipt day, not on the verdict day', function () {
    $row = Withdrawal::factory()->create();
    $receipt = $row->created_at?->toDateString() ?? '';

    $this->travel(3)->days();
    app(MarkWithdrawalAsSpam::class)->handle($row);

    expect(bucket($receipt, 'green', 'spam_operator'))->toBe(1)
        ->and(DB::table('withdrawal_daily_counts')->where('day', '!=', $receipt)->count())->toBe(0);
});

it('changes nothing when the same verdict is given again', function () {
    $row = Withdrawal::factory()->yellow()->create();
    $day = $row->created_at?->toDateString() ?? '';

    app(MarkWithdrawalAsSpam::class)->handle($row);
    $afterFirst = allBuckets();

    app(MarkWithdrawalAsSpam::class)->handle($row);

    app(MarkWithdrawalAsSpam::class)->handle($row->fresh());

    expect(allBuckets())->toEqual($afterFirst)
        ->and(bucket($day, 'yellow', 'spam_operator'))->toBe(1)
        ->and(bucket($day, 'yellow', 'spam_operator', 'throttle'))->toBe(1)
        ->and(bucket($day, 'yellow', 'none'))->toBe(0);
});

it('counts a spam row corrected back as not_spam, once', function () {
    $row = Withdrawal::factory()->create();
    $day = $row->created_at?->toDateString() ?? '';

    app(MarkWithdrawalAsSpam::class)->handle($row);
    app(MarkWithdrawalAsNotSpam::class)->handle($row);

    expect(bucket($day, 'green', 'none'))->toBe(0)
        ->and(bucket($day, 'green', 'spam_operator'))->toBe(0)
        ->and(bucket($day, 'green', 'not_spam'))->toBe(1);
});

it('does not touch the counters when other columns change', function () {
    $row = Withdrawal::factory()->create();
    $before = allBuckets();

    $row->handled_at = now();
    $row->save();

    expect(allBuckets())->toEqual($before);
});

it('keeps every count when the purge deletes the row', function () {
    $row = Withdrawal::factory()->confirmedSpam()->create();
    $day = $row->created_at?->toDateString() ?? '';
    $before = allBuckets();

    $this->travel(8)->days();
    Artisan::call('model:prune', ['--model' => Withdrawal::class]);

    expect(Withdrawal::count())->toBe(0)
        ->and(allBuckets())->toEqual($before)
        ->and(bucket($day, 'red', 'spam_operator'))->toBe(1)
        ->and(bucket($day, 'red', 'spam_operator', 'honeypot'))->toBe(1);
});

it('adds up the rows of one day', function () {
    Withdrawal::factory()->count(3)->create();

    expect(bucket(today()->toDateString(), 'green', 'none'))->toBe(3);
});

it('reports a counting failure and still stores the withdrawal and redirects', function () {
    Exceptions::fake();
    Schema::drop('withdrawal_daily_counts');

    submitCleanWithdrawal(countedPayload())->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::count())->toBe(1);
    Exceptions::assertReportedCount(1);
});

it('reports a failed move and still saves the verdict', function () {
    $row = Withdrawal::factory()->create();
    Exceptions::fake();
    Schema::drop('withdrawal_daily_counts');

    expect(app(MarkWithdrawalAsSpam::class)->handle($row))->toBeTrue()
        ->and($row->fresh()?->isConfirmedSpam())->toBeTrue();
    Exceptions::assertReportedCount(1);
});
