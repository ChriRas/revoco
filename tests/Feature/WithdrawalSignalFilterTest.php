<?php

declare(strict_types=1);

use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

/** A yellow withdrawal with the given reason, Inbox-visible. */
function withReason(string $reason): Withdrawal
{
    return Withdrawal::factory()->yellow()->create(['spam_reason' => $reason]);
}

it('shows only rows that carry one of the selected signals', function () {
    $honeypot = Withdrawal::factory()->spam()->create(['spam_reason' => 'honeypot,user_agent_ancient']);
    $throttle = withReason('name_without_space,throttle');
    $other = withReason('content_gibberish');
    $none = Withdrawal::factory()->create();

    livewire(ListWithdrawals::class)
        ->set('activeTab', 'spam')
        ->filterTable('signal', ['honeypot', 'throttle'])
        ->assertCanSeeTableRecords([$honeypot])
        ->assertCanNotSeeTableRecords([$throttle, $other, $none]);

    livewire(ListWithdrawals::class)
        ->filterTable('signal', ['honeypot', 'throttle'])
        ->assertCanSeeTableRecords([$throttle])
        ->assertCanNotSeeTableRecords([$honeypot, $other, $none]);
});

it('does not let one key match inside another', function () {
    $dropNetwork = withReason('drop_network');
    $asnDrop = withReason('asn_drop');

    livewire(ListWithdrawals::class)
        ->filterTable('signal', ['drop_network'])
        ->assertCanSeeTableRecords([$dropNetwork])
        ->assertCanNotSeeTableRecords([$asnDrop]);

    livewire(ListWithdrawals::class)
        ->filterTable('signal', ['asn_drop'])
        ->assertCanSeeTableRecords([$asnDrop])
        ->assertCanNotSeeTableRecords([$dropNetwork]);
});

it('treats a LIKE wildcard in a key literally', function () {
    $row = withReason('drop_network');

    livewire(ListWithdrawals::class)
        ->filterTable('signal', ['drop%'])
        ->assertCanNotSeeTableRecords([$row]);

    livewire(ListWithdrawals::class)
        ->filterTable('signal', ['dropXnetwork'])
        ->assertCanNotSeeTableRecords([$row]);
});

it('offers every weighted signal, labelled through the one rendering point', function () {
    livewire(ListWithdrawals::class)
        ->assertTableFilterExists('signal', function ($filter): bool {
            $options = $filter->getOptions();

            return array_keys($options) === array_keys(config('revoco.spam.weights'))
                && $options['honeypot'] === __('panel.spam_signal.honeypot');
        });
});

it('renders the filtered list from the URL form for a logged-in operator', function () {
    // The live preview defaults to the newest row of the tab whatever the filter says, so the match is the newest.
    Withdrawal::factory()->spam()->create(['spam_reason' => 'throttle', 'subject' => 'THROTTLE-ROW', 'created_at' => now()->subDay()]);
    Withdrawal::factory()->spam()->create(['spam_reason' => 'honeypot', 'subject' => 'HONEYPOT-ROW']);

    $this->get('/admin/withdrawals?tab=spam&filters[signal][values][0]=honeypot')
        ->assertOk()
        ->assertSee('HONEYPOT-ROW')
        ->assertDontSee('THROTTLE-ROW');
});
