<?php

declare(strict_types=1);

use App\Enums\SpamLevel;
use App\Models\SpamNetworkHit;
use App\Models\Withdrawal;
use App\Network\MmdbNetworkLookup;
use App\Network\NetworkInfo;
use App\Network\NetworkLookup;
use App\Settings\NetworkSignalSettings;
use App\Spam\Signals\CountryOutsideShipping;
use App\Spam\Signals\LearnedNetwork;
use App\Spam\SpamScorer;
use App\Spam\SubmissionContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    $this->networkDir = useNetworkDirectory();
    shippingCountries(['SE']);
});

afterEach(function (): void {
    TrustProxies::flushState();
});

/**
 * @param  array<string, string>  $server
 */
function submitFromIp(string $ip, array $server = []): TestResponse
{
    return submitCleanWithdrawal([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'website' => '',
    ], ['REMOTE_ADDR' => $ip] + $server);
}

/** The baseline every submit test starts from: a clean submit from a shipping country matches no signal. */
function assertCleanBaseline(): void
{
    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    $row = Withdrawal::query()->latest('id')->firstOrFail();
    expect($row->spam_score)->toBe(0)->and($row->spam_reason)->toBeNull();

    $row->delete();
}

// Lookup

it('resolves country, ASN and organisation locally from the fixture databases', function () {
    $info = app(NetworkLookup::class)->lookup(NETWORK_FIXTURE_IP_SE);

    expect($info)->toEqual(new NetworkInfo('SE', 29518, 'Bredband2 AB'))
        ->and(app(NetworkLookup::class)->lookup(NETWORK_FIXTURE_IP_US))->toEqual(new NetworkInfo('US', 209, null))
        ->and(app(NetworkLookup::class)->lookup(NETWORK_FIXTURE_IP_DE_V6))->toEqual(new NetworkInfo('DE', null, null));
});

it('resolves nothing for private, reserved, malformed, missing and unknown addresses', function (?string $ip) {
    expect(app(NetworkLookup::class)->lookup($ip))->toEqual(NetworkInfo::unknown());
})->with([null, '10.0.0.5', '192.168.1.1', '172.18.0.2', '127.0.0.1', 'fe80::1', 'not-an-ip', '']);

it('resolves nothing without a database and survives a corrupt one', function () {
    unlink($this->networkDir.'/dbip-country-lite.mmdb');
    file_put_contents($this->networkDir.'/dbip-asn-lite.mmdb', 'garbage');

    expect(app(NetworkLookup::class)->lookup(NETWORK_FIXTURE_IP_SE))->toEqual(NetworkInfo::unknown())
        ->and(app(MmdbNetworkLookup::class)->lookup('8.8.8.8'))->toEqual(NetworkInfo::unknown());
});

// Submit: country and ASN are stored, the IP is not

it('starts every case from a clean zero-score baseline', function () {
    assertCleanBaseline();
});

it('stores country and ASN of a shipping-country submit and no IP', function () {
    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    $row = Withdrawal::sole();
    expect($row->country)->toBe('SE')
        ->and($row->asn)->toBe(29518)
        ->and($row->asn_org)->toBe('Bredband2 AB')
        ->and($row->spam_score)->toBe(0)
        ->and($row->spam_reason)->toBeNull();

    expect(Schema::getColumnListing('withdrawals'))->toEqualCanonicalizing([
        'id', 'name', 'email', 'order_number', 'subject', 'locale', 'created_at', 'updated_at', 'handled_at',
        'spam_verdict', 'spam_verdict_at', 'spam_verdict_source', 'spam_level', 'spam_reason', 'spam_score', 'country', 'asn', 'asn_org',
    ]);
    expect(json_encode($row->getAttributes()))->not->toContain(NETWORK_FIXTURE_IP_SE);
});

it('fires only country_outside_shipping with its weight for a country outside the list', function () {
    assertCleanBaseline();
    shippingCountries(['DE']);

    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    $row = Withdrawal::sole();
    expect($row->spam_reason)->toBe('country_outside_shipping')
        ->and($row->spam_score)->toBe(config('revoco.spam.weights.country_outside_shipping'))
        ->and($row->country)->toBe('SE');
});

it('keeps the country signal off for an empty shipping list', function () {
    shippingCountries([]);

    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole())->spam_reason->toBeNull()->spam_score->toBe(0);
});

it('never penalises unknown data: private IP, missing database', function () {
    shippingCountries(['DE']);

    submitFromIp('10.1.2.3')->assertRedirect(route('withdrawal.success'));
    unlink($this->networkDir.'/dbip-country-lite.mmdb');
    unlink($this->networkDir.'/dbip-asn-lite.mmdb');
    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::count())->toBe(2);
    foreach (Withdrawal::all() as $row) {
        expect($row->country)->toBeNull()->and($row->asn)->toBeNull()
            ->and($row->spam_score)->toBe(0)->and($row->spam_reason)->toBeNull();
    }
});

// Feeds

it('fires only drop_network for an IP inside a listed block', function () {
    assertCleanBaseline();
    writeNetworkFeeds($this->networkDir, dropV4: ['89.160.20.0/24']);

    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole())->spam_reason->toBe('drop_network')->spam_score->toBe(30);
});

it('matches IPv6 blocks too', function () {
    shippingCountries(['DE']);
    writeNetworkFeeds($this->networkDir, dropV6: ['2a02:d180::/29']);

    submitFromIp(NETWORK_FIXTURE_IP_DE_V6)->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole())->spam_reason->toBe('drop_network');
});

it('fires only asn_drop for a listed ASN', function () {
    assertCleanBaseline();
    writeNetworkFeeds($this->networkDir, asnDrop: [64496, 29518]);

    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole())->spam_reason->toBe('asn_drop')->spam_score->toBe(20);
});

it('fires only tor_exit for a Tor exit address', function () {
    assertCleanBaseline();
    writeNetworkFeeds($this->networkDir, tor: ['198.51.100.9', NETWORK_FIXTURE_IP_SE]);

    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole())->spam_reason->toBe('tor_exit')->spam_score->toBe(5);
});

it('stays silent when the feed files are missing or unparsable', function () {
    file_put_contents($this->networkDir.'/drop.json', '{not json');
    file_put_contents($this->networkDir.'/asn-drop.json', '"x"');

    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole())->spam_reason->toBeNull()->spam_score->toBe(0);
});

// Combined: never red

it('scores all five network signals at 69, yellow, and still stores the row', function () {
    assertCleanBaseline();
    shippingCountries(['DE']);
    writeNetworkFeeds($this->networkDir, dropV4: ['89.160.20.0/24'], asnDrop: [29518], tor: [NETWORK_FIXTURE_IP_SE]);
    foreach ([1001, 1002, 1003] as $id) {
        SpamNetworkHit::create(['withdrawal_id' => $id, 'asn' => 29518, 'confirmed_at' => now()]);
    }

    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));

    $row = Withdrawal::sole();
    expect($row->spam_score)->toBe(69)
        ->and($row->spam_level)->toBe(SpamLevel::Yellow)
        ->and(explode(',', (string) $row->spam_reason))->toBe(['drop_network', 'asn_drop', 'learned_network', 'tor_exit', 'country_outside_shipping']);
});

it('keeps the sum of the network weights below the red threshold', function () {
    $keys = ['drop_network', 'asn_drop', 'learned_network', 'tor_exit', 'country_outside_shipping'];
    $sum = array_sum(array_map(fn (string $key): int => config('revoco.spam.weights.'.$key), $keys));

    expect($sum)->toBeLessThan(config('revoco.spam.thresholds.red'));
});

// A signal that cannot do its job does not fire and does not fail the submit

it('does not let a failing network signal reach the submit', function () {
    Schema::drop('spam_network_hits');
    $context = new SubmissionContext(
        name: 'Max Mustermann', orderNumber: null, subject: 'Schlauchboot', honeypotFilled: false, renderToken: null,
        userAgent: CLEAN_BROWSER_USER_AGENT, ip: NETWORK_FIXTURE_IP_SE, submittedAt: CarbonImmutable::now(),
        network: new NetworkInfo('SE', 29518, null),
    );

    expect((new LearnedNetwork)->fires($context))->toBeFalse();

    submitFromIp(NETWORK_FIXTURE_IP_SE)->assertRedirect(route('withdrawal.success'));
    expect(Withdrawal::count())->toBe(1);
});

it('does not fire the country signal when the settings cannot be read', function () {
    Schema::drop('settings');
    app()->forgetInstance(NetworkSignalSettings::class);
    $context = new SubmissionContext(
        name: 'Max Mustermann', orderNumber: null, subject: 'Schlauchboot', honeypotFilled: false, renderToken: null,
        userAgent: CLEAN_BROWSER_USER_AGENT, ip: NETWORK_FIXTURE_IP_US, submittedAt: CarbonImmutable::now(),
        network: new NetworkInfo('US', 209, null),
    );

    // The country is outside the saved ['SE'], so only a failing settings read can make this false.
    expect(fn () => app(NetworkSignalSettings::class)->shipping_countries)->toThrow(QueryException::class)
        ->and((new CountryOutsideShipping)->fires($context))->toBeFalse()
        ->and(app(SpamScorer::class)->assess($context)->signals)->not->toContain('country_outside_shipping');
});

// Trusted proxies

it('uses the forwarded client IP when proxies are trusted', function () {
    TrustProxies::at('*');

    submitFromIp('172.18.0.2', ['HTTP_X_FORWARDED_FOR' => NETWORK_FIXTURE_IP_SE])->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole())->country->toBe('SE')->asn->toBe(29518);
});

it('ignores X-Forwarded-For when no proxy is trusted', function () {
    submitFromIp('172.18.0.2', ['HTTP_X_FORWARDED_FOR' => NETWORK_FIXTURE_IP_SE])->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::sole())->country->toBeNull()->asn->toBeNull();
});
