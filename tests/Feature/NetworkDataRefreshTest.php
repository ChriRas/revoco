<?php

declare(strict_types=1);

use App\Jobs\RefreshNetworkData;
use App\Network\NetworkData;
use App\Network\NetworkLookup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const DBIP_COUNTRY_URL = 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz';
const DBIP_ASN_URL = 'https://download.db-ip.com/free/dbip-asn-lite-%s.mmdb.gz';
const DROP_V4_URL = 'https://www.spamhaus.org/drop/drop_v4.json';
const DROP_V6_URL = 'https://www.spamhaus.org/drop/drop_v6.json';
const ASN_DROP_URL = 'https://www.spamhaus.org/drop/asndrop.json';
const TOR_URL = 'https://check.torproject.org/torbulkexitlist';

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->travelTo('2026-10-13 12:00:00');
    $this->dir = useNetworkDirectory(withDatabases: false);
    $this->countryGz = gzencode((string) file_get_contents(__DIR__.'/../fixtures/network/GeoLite2-Country-Test.mmdb'));
    $this->asnGz = gzencode((string) file_get_contents(__DIR__.'/../fixtures/network/GeoLite2-ASN-Test.mmdb'));
});

/** Fakes all sources for the current month; `$overrides` replace single URLs. */
function fakeNetworkSources(array $overrides = []): void
{
    Http::fake($overrides + [
        sprintf(DBIP_COUNTRY_URL, '2026-10') => Http::response(test()->countryGz),
        sprintf(DBIP_ASN_URL, '2026-10') => Http::response(test()->asnGz),
        DROP_V4_URL => Http::response("{\"cidr\":\"89.160.20.0/24\",\"sblid\":\"SBL1\",\"rir\":\"ripencc\"}\n{\"type\":\"metadata\",\"timestamp\":1791897242,\"size\":1,\"records\":1}\n"),
        DROP_V6_URL => Http::response("{\"cidr\":\"2a02:d180::/29\",\"sblid\":\"SBL2\",\"rir\":\"ripencc\"}\n{\"type\":\"metadata\",\"timestamp\":1791897242,\"size\":1,\"records\":1}\n"),
        ASN_DROP_URL => Http::response("{\"asn\":245,\"rir\":\"arin\",\"domain\":\"example.com\",\"cc\":\"US\",\"asname\":\"X\"}\n{\"asn\":2601,\"rir\":\"ripencc\"}\n{\"type\":\"metadata\",\"timestamp\":1791897242}\n"),
        TOR_URL => Http::response("171.25.193.25\n80.67.167.81\n"),
    ]);
}

it('writes all files and meta.json', function () {
    fakeNetworkSources();

    $this->artisan('revoco:network-data:refresh', ['--no-interaction' => true])->assertSuccessful();

    foreach (['dbip-country-lite.mmdb', 'dbip-asn-lite.mmdb', 'drop.json', 'asn-drop.json', 'tor-exits.txt', 'meta.json'] as $file) {
        expect($this->dir.'/'.$file)->toBeFile();
    }
    expect(json_decode((string) file_get_contents($this->dir.'/drop.json'), true))->toBe(['v4' => ['89.160.20.0/24'], 'v6' => ['2a02:d180::/29']])
        ->and(json_decode((string) file_get_contents($this->dir.'/asn-drop.json'), true))->toBe([245, 2601])
        ->and(file_get_contents($this->dir.'/tor-exits.txt'))->toBe("171.25.193.25\n80.67.167.81\n");

    $meta = json_decode((string) file_get_contents($this->dir.'/meta.json'), true);
    expect($meta)->toHaveKeys(['geo', 'drop', 'asn-drop', 'tor'])
        ->and($meta['geo']['source'])->toBe('2026-10')
        ->and($meta['geo']['fetched_at'])->toBe('2026-10-13T10:00:00Z')
        ->and($meta['drop']['records'])->toBe(2)
        ->and($meta['drop']['source'])->toBe('2026-10-13T13:14:02Z')
        ->and($meta['asn-drop']['records'])->toBe(2)
        ->and($meta['tor']['records'])->toBe(2)
        ->and(app(NetworkData::class)->meta())->toBe($meta);
});

it('serves the refreshed data to the lookup and the signals', function () {
    fakeNetworkSources();
    $this->artisan('revoco:network-data:refresh')->assertSuccessful();

    $data = app(NetworkData::class);
    expect(app(NetworkLookup::class)->lookup(NETWORK_FIXTURE_IP_SE)->country)->toBe('SE')
        ->and($data->isInDrop('89.160.20.9'))->toBeTrue()
        ->and($data->isAsnListed(2601))->toBeTrue()
        ->and($data->isTorExit('171.25.193.25'))->toBeTrue();
});

it('falls back to the previous month when the current one is not published', function () {
    fakeNetworkSources([
        sprintf(DBIP_COUNTRY_URL, '2026-10') => Http::response('', 404),
        sprintf(DBIP_COUNTRY_URL, '2026-09') => Http::response($this->countryGz),
        sprintf(DBIP_ASN_URL, '2026-09') => Http::response($this->asnGz),
    ]);

    $this->artisan('revoco:network-data:refresh', ['--only' => 'geo'])->assertSuccessful();

    expect(app(NetworkData::class)->meta()['geo']['source'])->toBe('2026-09')
        ->and($this->dir.'/dbip-country-lite.mmdb')->toBeFile();
});

it('skips the download when the stored database is already the current month', function () {
    fakeNetworkSources();
    $this->artisan('revoco:network-data:refresh', ['--only' => 'geo'])->assertSuccessful();

    Http::fake();
    $this->artisan('revoco:network-data:refresh', ['--only' => 'geo'])->assertSuccessful();

    Http::assertNothingSent();
});

it('does not download the previous month again when the current one is still missing', function () {
    $this->travelTo('2026-11-01 04:15:00');
    fakeNetworkSources([
        sprintf(DBIP_COUNTRY_URL, '2026-11') => Http::response('', 404),
        sprintf(DBIP_COUNTRY_URL, '2026-10') => Http::response($this->countryGz),
        sprintf(DBIP_ASN_URL, '2026-10') => Http::response($this->asnGz),
    ]);
    $this->artisan('revoco:network-data:refresh', ['--only' => 'geo'])->assertSuccessful();

    Http::fake([sprintf(DBIP_COUNTRY_URL, '2026-11') => Http::response('', 404)]);
    $this->artisan('revoco:network-data:refresh', ['--only' => 'geo'])->assertSuccessful();

    Http::assertSentCount(1);
    expect(app(NetworkData::class)->meta()['geo']['source'])->toBe('2026-10');
});

it('keeps the previous file and refreshes the others when a source fails', function (string $kind) {
    fakeNetworkSources();
    $this->artisan('revoco:network-data:refresh')->assertSuccessful();
    $before = [
        'drop.json' => file_get_contents($this->dir.'/drop.json'),
        'dbip-country-lite.mmdb' => file_get_contents($this->dir.'/dbip-country-lite.mmdb'),
        'dbip-asn-lite.mmdb' => file_get_contents($this->dir.'/dbip-asn-lite.mmdb'),
    ];
    $this->travelTo('2026-11-02 04:15:00');

    $bad = match ($kind) {
        'http 500' => [DROP_V4_URL => Http::response('boom', 500)],
        'corrupt gz' => [sprintf(DBIP_COUNTRY_URL, '2026-11') => Http::response('not gzip')],
        'unparsable mmdb' => [sprintf(DBIP_COUNTRY_URL, '2026-11') => Http::response(gzencode('not an mmdb'))],
        'invalid feed' => [DROP_V6_URL => Http::response("{\"cidr\":\"not-a-cidr\"}\n")],
    };
    // A fresh client: the first matching stub wins, so the month-1 fakes would shadow the new ones.
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $good = [
        sprintf(DBIP_ASN_URL, '2026-11') => Http::response($this->asnGz),
        TOR_URL => Http::response("1.2.3.4\n"),
    ];
    Http::fake($bad + $good + [
        sprintf(DBIP_COUNTRY_URL, '2026-11') => Http::response($this->countryGz),
        DROP_V4_URL => Http::response("{\"cidr\":\"10.0.0.0/8\"}\n"),
        DROP_V6_URL => Http::response("{\"cidr\":\"2001:db8::/32\"}\n"),
        ASN_DROP_URL => Http::response("{\"asn\":7}\n"),
    ]);
    Log::spy();

    $this->artisan('revoco:network-data:refresh')->assertFailed();

    $kept = str_contains($kind, 'gz') || $kind === 'unparsable mmdb' ? ['dbip-country-lite.mmdb', 'dbip-asn-lite.mmdb'] : ['drop.json'];
    foreach ($kept as $file) {
        expect(file_get_contents($this->dir.'/'.$file))->toBe($before[$file]);
    }
    expect(file_get_contents($this->dir.'/tor-exits.txt'))->toBe("1.2.3.4\n")
        ->and(glob($this->dir.'/*.tmp'))->toBe([]);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'Network data refresh failed'));
})->with(['http 500', 'corrupt gz', 'unparsable mmdb', 'invalid feed']);

it('refreshes only the requested sources', function () {
    fakeNetworkSources();

    $this->artisan('revoco:network-data:refresh', ['--only' => 'tor,asn-drop'])->assertSuccessful();

    expect($this->dir.'/tor-exits.txt')->toBeFile()
        ->and($this->dir.'/asn-drop.json')->toBeFile()
        ->and(file_exists($this->dir.'/drop.json'))->toBeFalse();
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'db-ip.com'));
});

it('rejects an unknown source without hanging or downloading', function () {
    Http::fake();

    $this->artisan('revoco:network-data:refresh', ['--only' => 'nope', '--no-interaction' => true])->assertExitCode(2);

    Http::assertNothingSent();
});

it('schedules the refresh daily at 04:15 without overlap and the learned-network prune daily', function () {
    $events = collect(app(Schedule::class)->events());
    $refresh = $events->first(fn ($event) => str_contains((string) $event->command, 'revoco:network-data:refresh'));
    $prune = $events->first(fn ($event) => str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, 'SpamNetworkHit'));

    expect($refresh->expression)->toBe('15 4 * * *')
        ->and($refresh->withoutOverlapping)->toBeTrue()
        ->and($prune->expression)->toBe('0 0 * * *');
});

it('queues the refresh job from the command line job class', function () {
    fakeNetworkSources();

    dispatch_sync(new RefreshNetworkData);

    expect($this->dir.'/meta.json')->toBeFile();
});
