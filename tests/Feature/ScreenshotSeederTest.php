<?php

declare(strict_types=1);

use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Models\Withdrawal;
use App\Settings\LegalSettings;
use App\Settings\NotificationSettings;
use App\Settings\WithdrawalScopeSettings;
use App\Support\LegalContent;
use Database\Seeders\ScreenshotLegalSeeder;
use Database\Seeders\ScreenshotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

it('seeds the planned Inbox, Spam and released rows and nothing else', function (): void {
    $this->seed(ScreenshotSeeder::class);

    // 10 green/yellow Inbox rows (3 handled) plus the one released "not spam" row.
    expect(Withdrawal::inbox()->count())->toBe(11)
        ->and(Withdrawal::inbox()->whereNull('spam_verdict')->count())->toBe(10)
        ->and(Withdrawal::inbox()->whereNull('spam_verdict')->where('spam_level', SpamLevel::Yellow)->count())->toBe(2)
        ->and(Withdrawal::inbox()->whereNull('spam_verdict')->where('spam_level', SpamLevel::Green)->count())->toBe(8)
        ->and(Withdrawal::inbox()->whereNotNull('handled_at')->count())->toBe(3)
        ->and(Withdrawal::where('spam_verdict', SpamVerdict::NotSpam)->count())->toBe(1);

    // Spam tab: 2 red open, 1 confirmed by the operator, 2 auto-sorted; all younger than the purge age.
    expect(Withdrawal::spamFolder()->count())->toBe(5)
        ->and(Withdrawal::spamFolder()->whereNull('spam_verdict')->count())->toBe(2)
        ->and(Withdrawal::where('spam_verdict_source', SpamVerdictSource::Operator)->where('spam_verdict', SpamVerdict::Spam)->count())->toBe(1)
        ->and(Withdrawal::where('spam_verdict_source', SpamVerdictSource::Auto)->count())->toBe(2)
        ->and(Withdrawal::where('spam_level', SpamLevel::Red)->count())->toBe(6)
        ->and(Withdrawal::count())->toBe(16)
        ->and((new Withdrawal)->prunable()->count())->toBe(0);
});

it('uses fictitious addresses only', function (): void {
    $this->seed(ScreenshotSeeder::class);

    $foreign = Withdrawal::pluck('email')->filter(
        fn (string $email): bool => ! str_ends_with($email, '@example.com') && ! str_ends_with($email, '@example.org'),
    );

    expect($foreign)->toBeEmpty()
        ->and(Withdrawal::where('email', 'kunde@example.com')->where('name', 'Max Mustermann')->exists())->toBeTrue();
});

it('keeps twelve months of counter history of rows that no longer exist', function (): void {
    // Mid-month noon: the visible rows reach back into the previous month on the 1st otherwise.
    $this->travelTo(now()->setDay(15)->setTime(12, 0));

    $this->seed(ScreenshotSeeder::class);

    $totals = DB::table('withdrawal_daily_counts')->where('signal', '');
    $months = (clone $totals)->pluck('day')
        ->map(fn (string $day): string => substr($day, 0, 7))
        ->unique();

    expect($months)->toHaveCount(12)
        ->and($months->min())->toBe(now()->startOfMonth()->subMonths(11)->format('Y-m'))
        // The deleted historical rows were counted, so the counters exceed what the list still holds.
        ->and((int) (clone $totals)->sum('count'))->toBeGreaterThan(Withdrawal::count());
});

it('seeds the operator settings the screenshots show', function (): void {
    $this->seed(ScreenshotSeeder::class);

    expect(app(NotificationSettings::class)->refresh()->notification_email)->toEndWith('@example.com')
        ->and(app(WithdrawalScopeSettings::class)->refresh()->offers_goods)->toBeTrue()
        // The legal settings stay empty until the second seeder runs.
        ->and(LegalContent::isComplete())->toBeFalse();
});

it('fills the legal settings so the setup banner check passes', function (): void {
    $this->seed(ScreenshotLegalSeeder::class);

    app()->forgetInstance(LegalSettings::class);

    expect(LegalContent::isComplete())->toBeTrue()
        ->and(app(LegalSettings::class)->refresh()->imprint_name)->toBe('Muster Shop GmbH');
});

it('writes nothing in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    // --force passes the artisan production gate, so the seeders' own guard is what is under test.
    $this->artisan('db:seed', ['--class' => ScreenshotSeeder::class, '--force' => true])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => ScreenshotLegalSeeder::class, '--force' => true])->assertSuccessful();

    expect(Withdrawal::count())->toBe(0)
        ->and(DB::table('withdrawal_daily_counts')->count())->toBe(0)
        ->and(LegalContent::isComplete())->toBeFalse();
});
