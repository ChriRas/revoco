<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Enums\SpamLevel;
use App\Enums\SpamVerdict;
use App\Events\WithdrawalConfirmedAsSpam;
use App\Events\WithdrawalMarkedNotSpam;
use App\Events\WithdrawalSubmitted;
use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Filament\Resources\WithdrawalResource\Pages\ViewWithdrawal;
use App\Listeners\SendWithdrawalNotifications;
use App\Mail\WithdrawalAcknowledgment;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

// Tabs

it('lists green, yellow and corrected rows in the inbox, and red and confirmed rows under the spam tab', function () {
    $green = Withdrawal::factory()->create();
    $yellow = Withdrawal::factory()->yellow()->create();
    $corrected = Withdrawal::factory()->spam()->notSpam()->create();
    $red = Withdrawal::factory()->spam()->create();
    $confirmed = Withdrawal::factory()->confirmedSpam()->create();

    livewire(ListWithdrawals::class)
        ->assertSet('activeTab', 'inbox')
        ->assertCanSeeTableRecords([$green, $yellow, $corrected])
        ->assertCanNotSeeTableRecords([$red, $confirmed])
        ->set('activeTab', 'spam')
        ->assertCanSeeTableRecords([$red, $confirmed])
        ->assertCanNotSeeTableRecords([$green, $yellow, $corrected]);
});

it('keeps a confirmed-spam verdict in the spam tab even when the level is green', function () {
    $row = Withdrawal::factory()->create(['spam_verdict' => SpamVerdict::Spam, 'spam_verdict_at' => now()]);

    expect(Withdrawal::query()->inbox()->whereKey($row->id)->exists())->toBeFalse()
        ->and(Withdrawal::query()->spamFolder()->whereKey($row->id)->exists())->toBeTrue();
});

// Single actions

it('marks a row as spam, fires the event once and moves the row to the spam tab', function () {
    Event::fake([WithdrawalConfirmedAsSpam::class]);
    $row = Withdrawal::factory()->create();

    livewire(ListWithdrawals::class)
        ->callTableAction('mark_spam', $row)
        ->assertCanNotSeeTableRecords([$row])
        ->set('activeTab', 'spam')
        ->assertCanSeeTableRecords([$row]);

    $row->refresh();
    expect($row->spam_verdict)->toBe(SpamVerdict::Spam)
        ->and($row->spam_verdict_at)->not->toBeNull()
        ->and($row->isConfirmedSpam())->toBeTrue();
    Event::assertDispatchedTimes(WithdrawalConfirmedAsSpam::class, 1);
});

it('marks a red row as not spam, fires the event once and moves the row to the inbox', function () {
    Event::fake([WithdrawalMarkedNotSpam::class]);
    $row = Withdrawal::factory()->spam()->create();

    livewire(ListWithdrawals::class)
        ->set('activeTab', 'spam')
        ->callTableAction('not_spam', $row)
        ->assertCanNotSeeTableRecords([$row])
        ->set('activeTab', 'inbox')
        ->assertCanSeeTableRecords([$row]);

    $row->refresh();
    expect($row->spam_verdict)->toBe(SpamVerdict::NotSpam)
        ->and($row->spam_verdict_at)->not->toBeNull()
        ->and($row->spam_level)->toBe(SpamLevel::Red);
    Event::assertDispatchedTimes(WithdrawalMarkedNotSpam::class, 1);
});

it('treats a repeated verdict as a no-op without a second event', function () {
    Event::fake([WithdrawalConfirmedAsSpam::class, WithdrawalMarkedNotSpam::class]);
    $row = Withdrawal::factory()->yellow()->create();

    $this->travelTo(now()->startOfSecond());
    app(MarkWithdrawalAsSpam::class)->handle($row);
    $first = $row->fresh()?->spam_verdict_at;

    $this->travel(2)->hours();
    expect(app(MarkWithdrawalAsSpam::class)->handle($row))->toBeFalse();
    expect($row->fresh()?->spam_verdict_at?->equalTo($first))->toBeTrue();

    app(MarkWithdrawalAsNotSpam::class)->handle($row);
    expect(app(MarkWithdrawalAsNotSpam::class)->handle($row))->toBeFalse();

    Event::assertDispatchedTimes(WithdrawalConfirmedAsSpam::class, 1);
    Event::assertDispatchedTimes(WithdrawalMarkedNotSpam::class, 1);
});

it('shows every row action as an icon button and disables it where it would change nothing', function () {
    $green = Withdrawal::factory()->create();
    $confirmed = Withdrawal::factory()->confirmedSpam()->create();
    $corrected = Withdrawal::factory()->spam()->notSpam()->create();

    livewire(ListWithdrawals::class)
        ->assertTableActionVisible('mark_spam', $green)
        ->assertTableActionEnabled('mark_spam', $green)
        ->assertTableActionVisible('not_spam', $green)
        ->assertTableActionDisabled('not_spam', $green)
        ->assertTableActionEnabled('mark_spam', $corrected)
        ->assertTableActionDisabled('not_spam', $corrected)
        ->set('activeTab', 'spam')
        ->assertTableActionVisible('mark_spam', $confirmed)
        ->assertTableActionDisabled('mark_spam', $confirmed)
        ->assertTableActionEnabled('not_spam', $confirmed);
});

it('colours the icon of the current state and keeps the others neutral', function () {
    $green = Withdrawal::factory()->create();
    $confirmed = Withdrawal::factory()->confirmedSpam()->handled()->create();
    $corrected = Withdrawal::factory()->spam()->notSpam()->create();

    livewire(ListWithdrawals::class)
        ->assertTableActionHasColor('mark_spam', 'gray', $green)
        ->assertTableActionHasColor('not_spam', 'gray', $green)
        ->assertTableActionHasColor('toggle_handled', 'gray', $green)
        ->assertTableActionHasColor('view', 'gray', $green)
        ->assertTableActionHasColor('not_spam', 'success', $corrected)
        ->assertTableActionHasColor('mark_spam', 'gray', $corrected)
        ->set('activeTab', 'spam')
        ->assertTableActionHasColor('mark_spam', 'danger', $confirmed)
        ->assertTableActionHasColor('toggle_handled', 'success', $confirmed);
});

it('renders the row actions icon-only with a tooltip naming the action or the state', function () {
    $green = Withdrawal::factory()->create();
    $auto = Withdrawal::factory()->autoSorted()->create();

    $actions = collect(livewire(ListWithdrawals::class)->instance()->getTable()->getRecordActions())
        ->keyBy(fn ($action) => $action->getName());

    foreach (['view', 'mark_spam', 'not_spam', 'toggle_handled'] as $name) {
        expect($actions[$name]->record($green)->isIconButton())->toBeTrue("{$name} is not an icon button");
    }

    expect($actions['mark_spam']->record($green)->getTooltip())->toBe(__('panel.action.mark_spam'))
        ->and($actions['mark_spam']->record($auto)->getTooltip())->toBe(__('panel.spam_verdict.spam_auto'))
        ->and($actions['not_spam']->record($green)->getTooltip())->toBe(__('panel.action.not_spam'))
        ->and($actions['toggle_handled']->record($green)->getTooltip())->toBe(__('panel.action.mark_handled'))
        ->and($actions['view']->record($green)->getTooltip())->not->toBeEmpty();
});

it('runs both verdicts from the view page header', function () {
    Event::fake([WithdrawalConfirmedAsSpam::class, WithdrawalMarkedNotSpam::class]);
    $row = Withdrawal::factory()->yellow()->create();

    livewire(ViewWithdrawal::class, ['record' => $row->getKey()])
        ->callAction('mark_spam')
        ->assertActionHidden('mark_spam')
        ->callAction('not_spam');

    expect($row->fresh()?->spam_verdict)->toBe(SpamVerdict::NotSpam);
    Event::assertDispatched(WithdrawalConfirmedAsSpam::class);
    Event::assertDispatched(WithdrawalMarkedNotSpam::class);
});

// Bulk actions

it('confirms a selection as spam in bulk', function () {
    Event::fake([WithdrawalConfirmedAsSpam::class]);
    $rows = Withdrawal::factory()->count(3)->create();

    livewire(ListWithdrawals::class)
        ->callTableBulkAction('mark_spam', $rows);

    expect(Withdrawal::query()->where('spam_verdict', SpamVerdict::Spam)->count())->toBe(3);
    Event::assertDispatchedTimes(WithdrawalConfirmedAsSpam::class, 3);
});

it('skips rows in bulk where the verdict would change nothing', function () {
    Event::fake([WithdrawalMarkedNotSpam::class]);
    $green = Withdrawal::factory()->count(2)->create();
    $red = Withdrawal::factory()->spam()->count(2)->create();

    livewire(ListWithdrawals::class)
        ->callTableBulkAction('not_spam', $green);
    expect(Withdrawal::query()->whereNotNull('spam_verdict')->count())->toBe(0);

    livewire(ListWithdrawals::class)
        ->set('activeTab', 'spam')
        ->callTableBulkAction('not_spam', $green->concat($red));
    expect(Withdrawal::query()->where('spam_verdict', SpamVerdict::NotSpam)->count())->toBe(2);
    Event::assertDispatchedTimes(WithdrawalMarkedNotSpam::class, 2);
});

it('corrects a selection to not spam in bulk', function () {
    Event::fake([WithdrawalMarkedNotSpam::class]);
    $rows = Withdrawal::factory()->spam()->count(3)->create();

    livewire(ListWithdrawals::class)
        ->set('activeTab', 'spam')
        ->callTableBulkAction('not_spam', $rows);

    expect(Withdrawal::query()->where('spam_verdict', SpamVerdict::NotSpam)->count())->toBe(3);
    Event::assertDispatchedTimes(WithdrawalMarkedNotSpam::class, 3);
});

// Immutability of the declaration

it('leaves the declaration content untouched and offers no delete action', function () {
    $row = Withdrawal::factory()->create([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'order_number' => 'A-12345',
    ]);
    $before = $row->only(['name', 'email', 'order_number', 'subject', 'locale', 'created_at']);

    livewire(ListWithdrawals::class)
        ->callTableAction('mark_spam', $row)
        ->set('activeTab', 'spam')
        ->callTableAction('not_spam', $row)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    expect($row->fresh()?->only(['name', 'email', 'order_number', 'subject', 'locale', 'created_at']))->toEqual($before);
});

it('does not allow the verdict to be mass-assigned', function () {
    $row = Withdrawal::create([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'order_number' => null,
        'subject' => 'Test',
        'locale' => 'de',
        'spam_verdict' => 'spam',
        'spam_verdict_at' => now(),
    ]);

    expect($row->fresh()?->spam_verdict)->toBeNull()
        ->and($row->fresh()?->spam_verdict_at)->toBeNull();
});

// Submit path

it('stores a honeypot submit as red and still withholds the acknowledgment', function () {
    Mail::fake();

    submitCleanWithdrawal([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot',
        'website' => 'http://spam.example',
    ])->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', [
        'email' => 'kunde@example.com',
        'spam_level' => 'red',
        'spam_reason' => 'honeypot',
    ]);
    Mail::assertNotQueued(WithdrawalAcknowledgment::class);
    Mail::assertNothingSent();
});

it('still sends the acknowledgment for a yellow row', function () {
    Mail::fake();
    $row = Withdrawal::factory()->yellow()->create(['email' => 'kunde@example.com']);

    (new SendWithdrawalNotifications)->handle(new WithdrawalSubmitted($row));

    Mail::assertQueued(WithdrawalAcknowledgment::class);
});

// Migration

it('reverses and re-applies the migration, mapping the old spam flag to red or green', function () {
    $red = Withdrawal::factory()->spam()->create();
    $yellow = Withdrawal::factory()->yellow()->create();
    $green = Withdrawal::factory()->create();
    $corrected = Withdrawal::factory()->spam()->notSpam()->create();
    $confirmedGreen = Withdrawal::factory()->create(['spam_verdict' => SpamVerdict::Spam, 'spam_verdict_at' => now()]);

    // Step 9: slice-030's verdict-source column, slice-029's table, slice-028's three migrations (network columns, hits table,
    // shipping-countries setting), slice-026's two (acknowledgment log, digest-time setting) and slice-027's score column sort after
    // this one and roll back first.
    Artisan::call('migrate:rollback', ['--step' => 9]);

    expect(Schema::hasColumn('withdrawals', 'spam'))->toBeTrue()
        ->and(Schema::hasColumn('withdrawals', 'spam_level'))->toBeFalse()
        ->and((bool) DB::table('withdrawals')->where('id', $red->id)->value('spam'))->toBeTrue()
        ->and((bool) DB::table('withdrawals')->where('id', $yellow->id)->value('spam'))->toBeTrue()
        ->and((bool) DB::table('withdrawals')->where('id', $green->id)->value('spam'))->toBeFalse()
        ->and((bool) DB::table('withdrawals')->where('id', $corrected->id)->value('spam'))->toBeFalse()
        ->and((bool) DB::table('withdrawals')->where('id', $confirmedGreen->id)->value('spam'))->toBeTrue();

    Artisan::call('migrate');

    expect(Schema::hasColumn('withdrawals', 'spam'))->toBeFalse()
        ->and(DB::table('withdrawals')->where('id', $red->id)->value('spam_level'))->toBe('red')
        ->and(DB::table('withdrawals')->where('id', $yellow->id)->value('spam_level'))->toBe('red')
        ->and(DB::table('withdrawals')->where('id', $green->id)->value('spam_level'))->toBe('green');
});
