<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Enums\SpamLevel;
use App\Enums\SpamLinkAction;
use App\Enums\SpamVerdict;
use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Filament\Resources\WithdrawalResource\Pages\ViewWithdrawal;
use App\Mail\WithdrawalAcknowledgment;
use App\Models\AbusedAddress;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AbusedAddressMemory;
use App\Spam\SpamLinks;
use App\Support\AbusedAddressHasher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    useNetworkDirectory();
    shippingCountries(['DE']);
});

/** A clean submit from the persona; only the address varies. Returns the stored row. */
function submitFromAddress(string $email = 'kunde@example.com'): Withdrawal
{
    $before = Withdrawal::max('id') ?? 0;

    submitCleanWithdrawal([
        'name' => 'Max Mustermann', 'email' => $email, 'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X', 'website' => '',
    ])->assertRedirect(route('withdrawal.success'));

    return Withdrawal::query()->where('id', '>', $before)->sole();
}

function confirmSpamFrom(string $email = 'kunde@example.com'): Withdrawal
{
    $row = Withdrawal::factory()->create(['email' => $email]);
    app(MarkWithdrawalAsSpam::class)->handle($row);

    return $row;
}

it('remembers the normalised address as a hash on confirm, never in plaintext', function () {
    confirmSpamFrom(' Kunde@Example.com ');

    $entry = AbusedAddress::sole();

    expect($entry->hash)->toBe((new AbusedAddressHasher)->hash('kunde@example.com'));

    foreach ($entry->getAttributes() as $value) {
        expect(strtolower((string) $value))->not->toContain('kunde@example.com')->not->toContain('example.com');
    }
});

it('gives a hit yellow, exactly its weight, and still sends the acknowledgment', function () {
    confirmSpamFrom();

    $row = submitFromAddress();

    expect($row->spam_score)->toBe(config('revoco.spam.weights.abused_address'))->toBe(30)
        ->and($row->spam_reason)->toBe('abused_address')
        ->and($row->spam_level)->toBe(SpamLevel::Yellow);
    Mail::assertQueued(WithdrawalAcknowledgment::class);
});

it('adds exactly the hit weight to a clean baseline and stays below red', function () {
    $baseline = submitFromAddress('other@example.com');
    expect($baseline->spam_score)->toBe(0)->and($baseline->spam_level)->toBe(SpamLevel::Green);

    confirmSpamFrom();
    $hit = submitFromAddress();

    expect($hit->spam_score - $baseline->spam_score)->toBe(config('revoco.spam.weights.abused_address'))
        ->and($hit->spam_score)->toBeLessThan(config('revoco.spam.thresholds.red'));
});

it('keeps the weight inside the yellow band', function () {
    expect(config('revoco.spam.weights.abused_address'))
        ->toBeGreaterThanOrEqual(config('revoco.spam.thresholds.yellow'))
        ->toBeLessThan(config('revoco.spam.thresholds.red'));
});

it('matches the address regardless of case and whitespace', function () {
    confirmSpamFrom('kunde@example.com');

    expect(submitFromAddress('KUNDE@example.com')->spam_reason)->toBe('abused_address');
});

it('forgets the address on "not spam" in the panel', function () {
    $this->actingAs(User::factory()->create());
    $row = confirmSpamFrom();
    expect(AbusedAddress::count())->toBe(1);

    livewire(ListWithdrawals::class)->set('activeTab', 'spam')->callTableAction('not_spam', $row);

    expect(AbusedAddress::count())->toBe(0);
    $after = submitFromAddress();
    expect($after->spam_score)->toBe(0)->and($after->spam_reason)->toBeNull()->and($after->spam_level)->toBe(SpamLevel::Green);
});

it('forgets the address on "not spam" through the signed mail link', function () {
    $row = confirmSpamFrom();

    $this->post(SpamLinks::url($row->fresh(), SpamLinkAction::NotSpam))->assertRedirect();

    expect(AbusedAddress::count())->toBe(0);
    $after = submitFromAddress();
    expect($after->spam_score)->toBe(0)->and($after->spam_reason)->toBeNull();
});

it('remembers through the signed mail link confirm', function () {
    $row = Withdrawal::factory()->create(['email' => 'kunde@example.com']);

    $this->post(SpamLinks::url($row, SpamLinkAction::Spam))->assertRedirect();

    expect(AbusedAddress::count())->toBe(1);
});

it('forgets through the bulk "not spam" action', function () {
    $this->actingAs(User::factory()->create());
    $rows = [confirmSpamFrom('a@example.com'), confirmSpamFrom('b@example.com')];

    livewire(ListWithdrawals::class)->set('activeTab', 'spam')->callTableBulkAction('not_spam', collect($rows));

    expect(AbusedAddress::count())->toBe(0);
});

it('keeps one entry per distinct address on a bulk confirm', function () {
    $this->actingAs(User::factory()->create());
    $rows = collect([
        Withdrawal::factory()->create(['email' => 'kunde@example.com']),
        Withdrawal::factory()->create(['email' => 'Kunde@Example.com']),
        Withdrawal::factory()->create(['email' => 'andere@example.com']),
    ]);

    livewire(ListWithdrawals::class)->callTableBulkAction('mark_spam', $rows);

    expect(AbusedAddress::count())->toBe(2);
});

it('slides the expiry on a new confirmation', function () {
    $this->travelTo(now()->startOfSecond());
    confirmSpamFrom();
    $first = AbusedAddress::sole()->last_seen_at;

    $this->travel(11)->months();
    confirmSpamFrom();

    expect(AbusedAddress::count())->toBe(1)
        ->and(AbusedAddress::sole()->last_seen_at->greaterThan($first))->toBeTrue();

    $this->travel(2)->months();
    expect(app(AbusedAddressMemory::class)->isKnown('kunde@example.com'))->toBeTrue();
});

it('does not slide the expiry on a mere hit', function () {
    $this->travelTo(now()->startOfSecond());
    confirmSpamFrom();
    $first = AbusedAddress::sole()->last_seen_at;

    $this->travel(6)->months();
    submitFromAddress();

    expect(AbusedAddress::sole()->last_seen_at->equalTo($first))->toBeTrue();
});

it('ignores an entry past one year even before the prune runs, and prunes it', function () {
    confirmSpamFrom();

    $this->travel(1)->year();
    $this->travel(1)->day();

    expect(app(AbusedAddressMemory::class)->isKnown('kunde@example.com'))->toBeFalse()
        ->and(AbusedAddress::count())->toBe(1);

    $this->artisan('model:prune', ['--model' => AbusedAddress::class])->assertSuccessful();

    expect(AbusedAddress::count())->toBe(0);
});

it('keeps an entry that is still inside the year when pruning', function () {
    confirmSpamFrom();

    $this->travel(11)->months();
    $this->artisan('model:prune', ['--model' => AbusedAddress::class])->assertSuccessful();

    expect(AbusedAddress::count())->toBe(1);
});

it('schedules the daily prune of the memory', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'model:prune') && str_contains($event->command, 'AbusedAddress'));

    expect($events)->toHaveCount(1);
});

it('fails open: a throwing lookup still stores the row and redirects, with no hit', function () {
    confirmSpamFrom();

    app()->instance(AbusedAddressMemory::class, new class(new AbusedAddressHasher) extends AbusedAddressMemory
    {
        public function isKnown(string $email): bool
        {
            throw new RuntimeException('lookup down');
        }
    });

    $row = submitFromAddress();

    expect($row->spam_score)->toBe(0)
        ->and($row->spam_reason)->toBeNull()
        ->and($row->spam_level)->toBe(SpamLevel::Green);
});

it('renders the label, not the raw key, in the infolist', function (string $locale) {
    app()->setLocale($locale);
    $this->actingAs(User::factory()->create());
    $row = Withdrawal::factory()->create(['spam_reason' => 'abused_address', 'spam_score' => 30]);

    $html = livewire(ViewWithdrawal::class, ['record' => $row->getRouteKey()])->assertSuccessful()->html();

    expect($html)->not->toContain('abused_address')->toContain(__('panel.spam_signal.abused_address'));
    expect(__('panel.spam_signal.abused_address'))->toBe($locale === 'de' ? 'Adresse aus bestätigtem Spam bekannt' : 'Address known from confirmed spam');
})->with(['en', 'de']);

it('does nothing on a repeated verdict', function () {
    $this->travelTo(now()->startOfSecond());
    $row = confirmSpamFrom();
    $first = AbusedAddress::sole()->last_seen_at;

    $this->travel(3)->days();
    app(MarkWithdrawalAsSpam::class)->handle($row);
    expect(AbusedAddress::sole()->last_seen_at->equalTo($first))->toBeTrue();

    app(MarkWithdrawalAsNotSpam::class)->handle($row);
    app(MarkWithdrawalAsNotSpam::class)->handle($row);

    expect(AbusedAddress::count())->toBe(0);
});

it('remembers through the single-row panel action', function () {
    $this->actingAs(User::factory()->create());
    $row = Withdrawal::factory()->create(['email' => 'kunde@example.com']);

    livewire(ListWithdrawals::class)->callTableAction('mark_spam', $row);

    expect(AbusedAddress::count())->toBe(1);
});

it('keeps the verdict and the acknowledgment re-send when the memory fails on "not spam"', function () {
    $row = Withdrawal::factory()->spam()->create(['email' => 'kunde@example.com']);
    app(MarkWithdrawalAsSpam::class)->handle($row);

    $failing = new class(new AbusedAddressHasher) extends AbusedAddressMemory
    {
        public function remember(string $email): void
        {
            throw new RuntimeException('memory down');
        }

        public function forget(string $email): void
        {
            throw new RuntimeException('memory down');
        }
    };
    app()->instance(AbusedAddressMemory::class, $failing);

    expect(app(MarkWithdrawalAsNotSpam::class)->handle($row))->toBeTrue();

    expect($row->fresh()?->spam_verdict)->toBe(SpamVerdict::NotSpam);
    Mail::assertQueued(WithdrawalAcknowledgment::class);
});
