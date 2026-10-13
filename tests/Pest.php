<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
