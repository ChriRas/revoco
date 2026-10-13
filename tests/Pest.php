<?php

use App\Network\NetworkData;
use App\Settings\NetworkSignalSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit/Spam');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A current desktop browser user agent (Chrome 156 as of 2026-10; reduced UA format
 * "Chrome/<major>.0.0.0"). Pinned explicitly so submit assertions stay stable if the
 * user-agent rules tighten; Symfony's default test UA ("Symfony") is an unrecognised family.
 */
const CLEAN_BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36';

/**
 * Submits the withdrawal form the way a consumer does, so that no spam signal fires.
 *
 * It GETs `withdrawal.form`, takes the hidden `rendered` token from the HTML, travels the
 * clock forward by `revoco.spam.min_fill_seconds + 1` seconds and POSTs the payload plus the
 * token to `withdrawal.store` with a current desktop browser UA. A submit through it scores
 * 0 / green when nothing else fires.
 *
 * The clock moves forward and stays there (until the test ends): six submits move it 24 s
 * with the default of 3, which stays inside the 60 s throttle window.
 *
 * @param  array<string, mixed>  $payload  Form fields; a `rendered` key replaces the fetched token.
 * @param  array<string, string>  $server  Server variables for this request only (e.g. `REMOTE_ADDR`,
 *                                         `HTTP_USER_AGENT`); they do not persist into later requests.
 */
function submitCleanWithdrawal(array $payload, array $server = []): TestResponse
{
    $form = test()->withoutVite()->get(route('withdrawal.form'));
    $form->assertOk();

    preg_match('/name="rendered" value="([^"]*)"/', $form->getContent(), $matches);

    test()->travel((int) config('revoco.spam.min_fill_seconds') + 1)->seconds();

    return test()->call(
        'POST',
        route('withdrawal.store'),
        $payload + ['rendered' => html_entity_decode($matches[1] ?? '')],
        // The cookies queued by withCookie()/withUnencryptedCookie() travel with the POST like with post().
        cookies: (fn () => $this->prepareCookiesForRequest())->call(test()),
        server: $server + ['HTTP_USER_AGENT' => CLEAN_BROWSER_USER_AGENT],
    );
}

/*
|--------------------------------------------------------------------------
| Network fixtures (slice-028)
|--------------------------------------------------------------------------
|
| MaxMind's public test databases (tests/fixtures/network/README.md) stand in for the DB-IP
| files; the IPs below come from their published source data.
|
*/

/** Country SE, ASN 29518 "Bredband2 AB". */
const NETWORK_FIXTURE_IP_SE = '89.160.20.128';

/** Country US, ASN 209 (no organisation). */
const NETWORK_FIXTURE_IP_US = '216.160.83.56';

/** Country DE (IPv6), no ASN. */
const NETWORK_FIXTURE_IP_DE_V6 = '2a02:d180::1';

/**
 * Points `revoco.network.path` at a fresh temp directory (removed after the test) and, by default,
 * copies the two test MMDBs in under the names the refresh command writes.
 */
function useNetworkDirectory(bool $withDatabases = true): string
{
    $dir = sys_get_temp_dir().'/revoco-network-'.bin2hex(random_bytes(6));
    mkdir($dir, 0775, true);
    config(['revoco.network.path' => $dir]);

    if ($withDatabases) {
        copy(__DIR__.'/fixtures/network/GeoLite2-Country-Test.mmdb', $dir.'/dbip-country-lite.mmdb');
        copy(__DIR__.'/fixtures/network/GeoLite2-ASN-Test.mmdb', $dir.'/dbip-asn-lite.mmdb');
    }

    test()->beforeApplicationDestroyed(function () use ($dir): void {
        File::deleteDirectory($dir);
    });

    return $dir;
}

/**
 * Writes the normalised feed files the refresh produces.
 *
 * @param  list<string>  $dropV4
 * @param  list<string>  $dropV6
 * @param  list<int>  $asnDrop
 * @param  list<string>  $tor
 */
function writeNetworkFeeds(string $dir, array $dropV4 = [], array $dropV6 = [], array $asnDrop = [], array $tor = []): void
{
    file_put_contents($dir.'/drop.json', json_encode(['v4' => $dropV4, 'v6' => $dropV6]));
    file_put_contents($dir.'/asn-drop.json', json_encode($asnDrop));
    file_put_contents($dir.'/tor-exits.txt', implode("\n", $tor)."\n");

    // The feeds are read once per instance (per request in production); the test app outlives requests.
    app(NetworkData::class)->flush();
}

/** Sets the operator's shipping countries. */
function shippingCountries(array $codes): void
{
    $settings = app(NetworkSignalSettings::class);
    $settings->shipping_countries = $codes;
    $settings->save();
}
