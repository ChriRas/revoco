<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Actions\MarkWithdrawalAsSpam;
use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Models\SpamNetworkHit;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    useNetworkDirectory();
    shippingCountries(['SE']);
});

/** @return list<Withdrawal> */
function rowsInAsn(int $count, int $asn = 29518): array
{
    return Withdrawal::factory()->count($count)->fromNetwork('SE', $asn, 'Bredband2 AB')->create()->all();
}

/** A clean submit from the fixture IP; returns the stored row. */
function submitFromLearnedIp(): Withdrawal
{
    $before = Withdrawal::max('id') ?? 0;
    submitCleanWithdrawal([
        'name' => 'Max Mustermann', 'email' => 'kunde@example.com', 'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X', 'website' => '',
    ], ['REMOTE_ADDR' => NETWORK_FIXTURE_IP_SE])->assertRedirect(route('withdrawal.success'));

    return Withdrawal::query()->where('id', '>', $before)->sole();
}

it('records one hit per confirmed row', function () {
    [$a, $b] = rowsInAsn(2);

    app(MarkWithdrawalAsSpam::class)->handle($a);
    app(MarkWithdrawalAsSpam::class)->handle($a);
    app(MarkWithdrawalAsSpam::class)->handle($b);

    expect(SpamNetworkHit::count())->toBe(2)
        ->and(SpamNetworkHit::pluck('asn')->unique()->all())->toBe([29518]);
});

it('skips rows without an ASN', function () {
    $row = Withdrawal::factory()->create();

    app(MarkWithdrawalAsSpam::class)->handle($row);

    expect(SpamNetworkHit::count())->toBe(0);
});

it('fires learned_network from the third confirmed row of an ASN, via single and bulk actions', function () {
    $this->actingAs(User::factory()->create());
    [$a, $b, $c] = rowsInAsn(3);

    livewire(ListWithdrawals::class)->callTableAction('mark_spam', $a);
    livewire(ListWithdrawals::class)->callTableBulkAction('mark_spam', collect([$b]));
    expect(submitFromLearnedIp()->spam_reason)->toBeNull();

    livewire(ListWithdrawals::class)->callTableBulkAction('mark_spam', collect([$c]));
    $row = submitFromLearnedIp();

    expect($row->spam_reason)->toBe('learned_network')->and($row->spam_score)->toBe(10);
});

it('does not learn from another ASN', function () {
    foreach (rowsInAsn(3, asn: 209) as $row) {
        app(MarkWithdrawalAsSpam::class)->handle($row);
    }

    expect(submitFromLearnedIp()->spam_reason)->toBeNull();
});

it('takes the hit back on "not spam" and drops below the threshold', function () {
    $rows = rowsInAsn(3);
    foreach ($rows as $row) {
        app(MarkWithdrawalAsSpam::class)->handle($row);
    }

    app(MarkWithdrawalAsNotSpam::class)->handle($rows[0]);

    expect(SpamNetworkHit::count())->toBe(2)
        ->and(SpamNetworkHit::whereKey($rows[0]->id)->exists())->toBeFalse()
        ->and(submitFromLearnedIp()->spam_reason)->toBeNull();
});

it('changes nothing on "not spam" for a row that was never confirmed', function () {
    $rows = rowsInAsn(3);
    foreach (array_slice($rows, 0, 2) as $row) {
        app(MarkWithdrawalAsSpam::class)->handle($row);
    }
    $other = Withdrawal::factory()->yellow()->fromNetwork('SE', 29518)->create();

    app(MarkWithdrawalAsNotSpam::class)->handle($other);

    expect(SpamNetworkHit::count())->toBe(2);
});

it('keeps the hits when the 7-day purge removes the confirmed rows', function () {
    $rows = rowsInAsn(3);
    foreach ($rows as $row) {
        app(MarkWithdrawalAsSpam::class)->handle($row);
    }

    $this->travel(8)->days();
    $this->artisan('model:prune', ['--model' => Withdrawal::class])->assertSuccessful();

    expect(Withdrawal::count())->toBe(0)
        ->and(SpamNetworkHit::count())->toBe(3);
    // Withdrawal ids of the purged rows are never reused; the signal still sees the hits.
    expect(submitFromLearnedIp()->spam_reason)->toBe('learned_network');
});

it('stops counting hits after 90 days and prunes them', function () {
    foreach (rowsInAsn(3) as $row) {
        app(MarkWithdrawalAsSpam::class)->handle($row);
    }

    $this->travel(89)->days();
    expect(submitFromLearnedIp()->spam_reason)->toBe('learned_network');

    $this->travel(2)->days();
    expect(submitFromLearnedIp()->spam_reason)->toBeNull();

    $this->artisan('model:prune', ['--model' => SpamNetworkHit::class])->assertSuccessful();
    expect(SpamNetworkHit::count())->toBe(0);
});

it('refreshes the hit when the same row is confirmed again after a correction', function () {
    $row = rowsInAsn(1)[0];

    app(MarkWithdrawalAsSpam::class)->handle($row);
    $this->travel(10)->days();
    app(MarkWithdrawalAsNotSpam::class)->handle($row->refresh());
    app(MarkWithdrawalAsSpam::class)->handle($row->refresh());

    expect(SpamNetworkHit::count())->toBe(1)
        ->and(SpamNetworkHit::sole()->confirmed_at->isSameDay(now()))->toBeTrue();
});
