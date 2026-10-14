<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Events\WithdrawalConfirmedAsSpam;
use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Filament\Resources\WithdrawalResource\Pages\ViewWithdrawal;
use App\Mail\WithdrawalAcknowledgment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Spam\Signals\Honeypot;
use App\Spam\SpamScorer;
use App\Spam\SpamSignal;
use App\Spam\SubmissionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

/**
 * @return array<string, string>
 */
function honeypotPayload(): array
{
    return [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'website' => 'https://spam.example',
    ];
}

it('sorts out a honeypot submit from an ancient browser as automatic spam, still stored and redirected', function () {
    Event::fake([WithdrawalConfirmedAsSpam::class]);

    submitCleanWithdrawal(honeypotPayload(), ['HTTP_USER_AGENT' => ANCIENT_CHROME_USER_AGENT])
        ->assertRedirect(route('withdrawal.success'));

    $row = Withdrawal::sole();
    expect($row->spam_level)->toBe(SpamLevel::Red)
        ->and($row->spam_verdict)->toBe(SpamVerdict::Spam)
        ->and($row->spam_verdict_at)->not->toBeNull()
        ->and($row->spam_verdict_source)->toBe(SpamVerdictSource::Auto)
        ->and($row->isAutoSorted())->toBeTrue()
        ->and(Withdrawal::query()->spamFolder()->whereKey($row->id)->exists())->toBeTrue();

    // No memory feeding, and red routing: no acknowledgment, no merchant mail.
    Event::assertNotDispatched(WithdrawalConfirmedAsSpam::class);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('keeps a honeypot submit from a current browser as an unjudged red row', function () {
    submitCleanWithdrawal(honeypotPayload())->assertRedirect(route('withdrawal.success'));

    $row = Withdrawal::sole();
    expect($row->spam_level)->toBe(SpamLevel::Red)
        ->and($row->spam_verdict)->toBeNull()
        ->and($row->spam_verdict_source)->toBeNull();
});

it('prunes an auto-sorted row after 7 days like confirmed spam', function () {
    $row = Withdrawal::factory()->autoSorted(now())->create();

    $this->travel(6)->days();
    Artisan::call('model:prune', ['--model' => Withdrawal::class]);
    expect(Withdrawal::find($row->id))->not->toBeNull();

    $this->travel(2)->days();
    Artisan::call('model:prune', ['--model' => Withdrawal::class]);
    expect(Withdrawal::find($row->id))->toBeNull();
});

it('lets the operator correct an auto-sorted row to not spam, which releases the acknowledgment', function () {
    $row = Withdrawal::factory()->autoSorted()->create(['email' => 'kunde@example.com']);

    expect(app(MarkWithdrawalAsNotSpam::class)->handle($row))->toBeTrue();

    $row->refresh();
    expect($row->spam_verdict)->toBe(SpamVerdict::NotSpam)
        ->and($row->spam_verdict_source)->toBe(SpamVerdictSource::Operator)
        ->and($row->isAutoSorted())->toBeFalse();
    Mail::assertQueued(WithdrawalAcknowledgment::class, fn (WithdrawalAcknowledgment $mail): bool => $mail->hasTo('kunde@example.com'));
});

it('labels an auto-sorted row as automatic spam in the list and the detail view', function () {
    $this->actingAs(User::factory()->create());
    $auto = Withdrawal::factory()->autoSorted()->create();
    $operator = Withdrawal::factory()->confirmedSpam()->create();

    expect($auto->verdictLabel())->toBe(__('panel.spam_verdict.spam_auto'))
        ->and($operator->verdictLabel())->toBe(__('panel.spam_verdict.spam'));

    livewire(ListWithdrawals::class)
        ->set('activeTab', 'spam')
        ->assertCanSeeTableRecords([$auto, $operator])
        ->assertSee(__('panel.spam_verdict.spam_auto'));

    livewire(ViewWithdrawal::class, ['record' => $auto->getKey()])
        ->assertSee(__('panel.spam_verdict.spam_auto'));
});

it('stores the submit and redirects when a spam signal throws', function () {
    Exceptions::fake();
    $this->app->bind(SpamScorer::class, fn (): SpamScorer => new SpamScorer([
        new readonly class implements SpamSignal
        {
            public function key(): string
            {
                return 'throttle';
            }

            public function fires(SubmissionContext $context): bool
            {
                throw new RuntimeException('database is locked');
            }
        },
        app(Honeypot::class),
    ]));

    submitCleanWithdrawal(honeypotPayload())->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole()->spam_reason)->toBe('honeypot');
    Exceptions::assertReported(RuntimeException::class);
});
