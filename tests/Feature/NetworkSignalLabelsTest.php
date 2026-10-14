<?php

declare(strict_types=1);

use App\Filament\Resources\WithdrawalResource\Pages\ViewWithdrawal;
use App\Models\User;
use App\Models\Withdrawal;
use App\Spam\SpamReasonLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

const NETWORK_SIGNAL_KEYS = ['country_outside_shipping', 'drop_network', 'asn_drop', 'tor_exit', 'learned_network'];

it('has a label for each network signal in en and de', function () {
    foreach (['en', 'de'] as $locale) {
        $labels = require lang_path($locale.'/panel.php');

        foreach (NETWORK_SIGNAL_KEYS as $key) {
            expect($labels['spam_signal'])->toHaveKey($key);
            expect($labels['spam_signal'][$key])->toBeString()->not->toBe('');
        }
    }
});

it('states the neutral wording of the plan', function () {
    app()->setLocale('en');
    expect(SpamReasonLabels::render(implode(',', NETWORK_SIGNAL_KEYS)))->toBe(
        'Country outside the shipping countries, IP address in a listed network, Listed network operator, Tor exit node, Network with repeatedly confirmed spam',
    );

    app()->setLocale('de');
    expect(SpamReasonLabels::render(implode(',', NETWORK_SIGNAL_KEYS)))->toBe(
        'Land außerhalb der Versandländer, IP-Adresse in einem gelisteten Netz, Gelisteter Netzbetreiber, Tor-Exit-Knoten, Netz mit wiederholt bestätigtem Spam',
    );
});

it('renders the labels, not the raw keys, in the withdrawal infolist', function (string $locale) {
    app()->setLocale($locale);
    $this->actingAs(User::factory()->create());
    $row = Withdrawal::factory()->create(['spam_reason' => implode(',', NETWORK_SIGNAL_KEYS), 'spam_score' => 69]);

    $html = livewire(ViewWithdrawal::class, ['record' => $row->getRouteKey()])->assertSuccessful()->html();

    foreach (NETWORK_SIGNAL_KEYS as $key) {
        expect($html)->not->toContain($key)->toContain(__('panel.spam_signal.'.$key));
    }
})->with(['en', 'de']);
