<?php

declare(strict_types=1);

use App\Filament\Pages\ManageNetworkSignals;
use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Filament\Resources\WithdrawalResource\Pages\ViewWithdrawal;
use App\Jobs\RefreshNetworkData;
use App\Models\User;
use App\Models\Withdrawal;
use App\Settings\NetworkSignalSettings;
use App\Support\CountryNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('defaults the shipping countries to DE', function () {
    expect(app(NetworkSignalSettings::class)->shipping_countries)->toBe(['DE']);
});

it('saves the shipping countries', function () {
    livewire(ManageNetworkSignals::class)
        ->assertOk()
        ->assertFormSet(['shipping_countries' => ['DE']])
        ->fillForm(['shipping_countries' => ['DE', 'AT']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(NetworkSignalSettings::class)->shipping_countries)->toBe(['DE', 'AT']);
});

it('accepts an empty list, which switches the country signal off', function () {
    livewire(ManageNetworkSignals::class)
        ->fillForm(['shipping_countries' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(NetworkSignalSettings::class)->shipping_countries)->toBe([]);
});

it('lists country names in the panel locale', function () {
    expect(CountryNames::options('de'))->toHaveKey('DE', 'Deutschland')->toHaveKey('AT', 'Österreich')->not->toHaveKeys(['EU', 'ZZ', 'UN'])
        ->and(CountryNames::options('en'))->toHaveKey('DE', 'Germany');

    app()->setLocale('de');
    livewire(ManageNetworkSignals::class)->assertSee('Versandländer');
});

it('shows the data status and the DB-IP attribution on the settings page', function () {
    $dir = useNetworkDirectory(withDatabases: false);
    file_put_contents($dir.'/meta.json', json_encode(['tor' => ['fetched_at' => '2026-10-13T10:00:00Z', 'records' => 1234, 'source' => null]]));
    app()->setLocale('en');

    livewire(ManageNetworkSignals::class)
        ->assertSee('IP Geolocation by DB-IP')
        ->assertSeeHtml('href="https://db-ip.com"')
        ->assertSee('13.10.2026 12:00')
        ->assertSee('1.234')
        ->assertSee('never');
});

it('queues a refresh from the "Refresh now" action', function () {
    Queue::fake();

    livewire(ManageNetworkSignals::class)->callAction('refreshNow');

    Queue::assertPushed(RefreshNetworkData::class);
});

it('shows country, network and the DB-IP attribution on the withdrawal view', function () {
    app()->setLocale('en');
    $row = Withdrawal::factory()->fromNetwork('DE', 3320, 'Deutsche Telekom AG')->create();

    livewire(ViewWithdrawal::class, ['record' => $row->getRouteKey()])
        ->assertSeeTextInOrder(['Country', 'DE', 'Network', 'AS3320 Deutsche Telekom AG'])
        ->assertSee('IP Geolocation by DB-IP')
        ->assertSeeHtml('href="https://db-ip.com"');
});

it('shows a dash for a row without network data', function () {
    app()->setLocale('en');
    $row = Withdrawal::factory()->create();

    livewire(ViewWithdrawal::class, ['record' => $row->getRouteKey()])
        ->assertDontSee('AS ')
        ->assertSeeTextInOrder(['Country', '—', 'Network', '—']);
});

it('offers the country as a toggleable column, hidden by default', function () {
    Withdrawal::factory()->fromNetwork('DE', 3320)->create();

    $column = livewire(ListWithdrawals::class)
        ->assertTableColumnExists('country')
        ->instance()->getTable()->getColumn('country');

    expect($column->isToggleable())->toBeTrue()->and($column->isToggledHiddenByDefault())->toBeTrue();
});

it('does not carry the DB-IP link on the public form or the success page', function () {
    $this->withoutVite()->get(route('withdrawal.form'))->assertOk()->assertDontSee('db-ip.com');
    $this->withoutVite()->get(route('withdrawal.success'))->assertOk()->assertDontSee('db-ip.com');
});
