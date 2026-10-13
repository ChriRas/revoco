<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Enums\AcknowledgmentTrigger;
use App\Enums\SpamLinkAction;
use App\Events\WithdrawalSubmitted;
use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Mail\WithdrawalAcknowledgment;
use App\Models\AcknowledgmentLog;
use App\Models\User;
use App\Models\Withdrawal;
use App\Spam\SpamLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    config(['revoco.merchant_email' => null, 'revoco.ntfy.enabled' => false]);
});

it('queues one acknowledgment for a red row when its digest link says not spam', function () {
    $this->travelTo(Carbon::create(2026, 9, 1, 10, 0, 0, 'Europe/Berlin'));
    $row = Withdrawal::factory()->spam()->create(['email' => 'kunde@example.com', 'locale' => 'en']);
    WithdrawalSubmitted::dispatch($row);
    Mail::assertNothingQueued();

    $this->travelTo(Carbon::create(2026, 9, 3, 7, 0, 0, 'Europe/Berlin'));
    $this->post(SpamLinks::url($row, SpamLinkAction::NotSpam))->assertRedirect();

    Mail::assertQueued(WithdrawalAcknowledgment::class, 1);
    Mail::assertQueued(
        WithdrawalAcknowledgment::class,
        fn (WithdrawalAcknowledgment $m) => $m->hasTo('kunde@example.com') && $m->locale === 'en',
    );

    // The mail shows the original receipt time, not the time of the correction.
    $queued = Mail::queued(WithdrawalAcknowledgment::class)->first();
    expect($queued)->not->toBeNull();
    $queued->locale('de')->assertSeeInHtml('01.09.2026, 10:00');

    $entry = AcknowledgmentLog::where('withdrawal_id', $row->id)->sole();
    expect($entry->trigger)->toBe(AcknowledgmentTrigger::NotSpam);
});

it('sends no second acknowledgment when the verdict flips back and forth', function () {
    $row = Withdrawal::factory()->spam()->create();

    app(MarkWithdrawalAsNotSpam::class)->handle($row);
    app(MarkWithdrawalAsSpam::class)->handle($row);
    app(MarkWithdrawalAsNotSpam::class)->handle($row);

    Mail::assertQueued(WithdrawalAcknowledgment::class, 1);
});

it('sends nothing on not spam for a yellow row that was acknowledged at submit', function () {
    $row = Withdrawal::factory()->yellow()->create();
    WithdrawalSubmitted::dispatch($row);
    Mail::assertQueued(WithdrawalAcknowledgment::class, 1);

    app(MarkWithdrawalAsSpam::class)->handle($row);
    app(MarkWithdrawalAsNotSpam::class)->handle($row);

    Mail::assertQueued(WithdrawalAcknowledgment::class, 1);
});

it('re-sends once per withheld row on the panel bulk not-spam action', function () {
    $this->actingAs(User::factory()->create());
    $red = Withdrawal::factory()->spam()->count(2)->create();
    $yellow = Withdrawal::factory()->yellow()->create();
    WithdrawalSubmitted::dispatch($yellow);
    Mail::assertQueued(WithdrawalAcknowledgment::class, 1);

    livewire(ListWithdrawals::class)
        ->set('activeTab', 'spam')
        ->callTableBulkAction('not_spam', $red);
    livewire(ListWithdrawals::class)
        ->callTableBulkAction('not_spam', collect([$yellow]));

    Mail::assertQueued(WithdrawalAcknowledgment::class, 3);
    foreach ($red as $row) {
        Mail::assertQueued(WithdrawalAcknowledgment::class, fn (WithdrawalAcknowledgment $m) => $m->hasTo($row->email));
    }
});

it('backfills a submit entry for every existing row that is not red', function () {
    $green = Withdrawal::factory()->create();
    $yellow = Withdrawal::factory()->yellow()->create();
    $confirmedGreen = Withdrawal::factory()->create(['spam_verdict' => 'spam', 'spam_verdict_at' => now()]);
    $red = Withdrawal::factory()->spam()->create();
    $correctedRed = Withdrawal::factory()->spam()->notSpam()->create();

    // Step 7: slice-029's table, slice-028's three migrations (network columns, hits table, shipping-countries setting), the score
    // column (slice-027) and the digest-time settings migration roll back before the log migration.
    Artisan::call('migrate:rollback', ['--step' => 7]);
    expect(Schema::hasTable('withdrawal_acknowledgments'))->toBeFalse();
    Artisan::call('migrate');

    $logged = AcknowledgmentLog::pluck('trigger', 'withdrawal_id');
    expect($logged->keys()->sort()->values()->all())->toBe(collect([$green, $yellow, $confirmedGreen])->pluck('id')->sort()->values()->all())
        ->and($logged->get($green->id))->toBe(AcknowledgmentTrigger::Submit)
        ->and($logged->has($red->id))->toBeFalse()
        ->and($logged->has($correctedRed->id))->toBeFalse();
});
