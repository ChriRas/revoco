<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsSpam;
use App\Enums\SpamVerdict;
use App\Enums\SpamVerdictSource;
use App\Mail\SpamDigest;
use App\Mail\WithdrawalAcknowledgment;
use App\Mail\WithdrawalNotification;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * End-to-end spam simulation (slice-030): every scenario plays one submit the way a browser or a
 * bot does — GET the form, take the render token, wait (or not), POST from a given IP and user
 * agent — and checks the stored classification and which mails go out. It replaces the manual
 * scratchpad script of the local hands-on test. The MaxMind test databases stand in for the
 * network data (see tests/Pest.php); the operator ships to Germany only.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    config(['revoco.merchant_email' => 'merchant@example.com']);
    $this->networkDir = useNetworkDirectory();
    writeNetworkFeeds($this->networkDir);
    shippingCountries(['DE']);
});

/**
 * Plays one submit. Defaults are a genuine customer: German IP, current browser, a filled-in form
 * and a fill time above the minimum.
 *
 * @param  array<string, mixed>  $overrides  form fields plus `ip`, `ua`, `wait` (bool), `token` (bool)
 */
function simulateSubmit(array $overrides = []): TestResponse
{
    $s = array_merge([
        'ip' => NETWORK_FIXTURE_IP_DE_V6,
        'ua' => CLEAN_BROWSER_USER_AGENT,
        'wait' => true,
        'token' => true,
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => '10001',
        'subject' => 'Bluetooth-Kopfhörer, schwarz',
        'website' => '',
    ], $overrides);

    $server = ['REMOTE_ADDR' => $s['ip'], 'HTTP_USER_AGENT' => $s['ua']];

    $form = test()->withoutVite()->call('GET', route('withdrawal.form'), server: $server);
    $form->assertOk();
    preg_match('/name="rendered" value="([^"]*)"/', (string) $form->getContent(), $matches);

    if ($s['wait']) {
        test()->travel((int) config('revoco.spam.min_fill_seconds') + 1)->seconds();
    }

    $payload = array_intersect_key($s, array_flip(['name', 'email', 'orderNumber', 'subject', 'website']));
    if ($s['token']) {
        $payload['rendered'] = html_entity_decode($matches[1] ?? '');
    }

    return test()->call(
        'POST',
        route('withdrawal.store'),
        $payload,
        cookies: (fn () => $this->prepareCookiesForRequest())->call(test()),
        server: $server,
    );
}

function latestWithdrawal(): Withdrawal
{
    return Withdrawal::query()->latest('id')->firstOrFail();
}

/** Asserts which mails the submit of `$row` queued: the acknowledgment and the merchant notification go together. */
function assertDelivered(Withdrawal $row, bool $delivered): void
{
    $ack = Mail::queued(WithdrawalAcknowledgment::class, fn (WithdrawalAcknowledgment $m): bool => $m->withdrawal->is($row))->count();
    $merchant = Mail::queued(WithdrawalNotification::class, fn (WithdrawalNotification $m): bool => $m->withdrawal->is($row))->count();

    expect([$ack, $merchant])->toBe($delivered ? [1, 1] : [0, 0]);
}

it('classifies each simulated submit and routes its mails accordingly', function (
    array $submit,
    ?Closure $arrange,
    string $level,
    int $score,
    ?string $reason,
    ?SpamVerdictSource $autoVerdict,
) {
    if ($arrange !== null) {
        $arrange($this->networkDir);
    }

    simulateSubmit($submit)->assertRedirect(route('withdrawal.success'));

    $row = latestWithdrawal();
    expect($row->spam_level->value)->toBe($level)
        ->and($row->spam_score)->toBe($score)
        ->and($row->spam_reason)->toBe($reason);

    if ($autoVerdict === null) {
        expect($row->spam_verdict)->toBeNull();
    } else {
        expect($row->spam_verdict)->toBe(SpamVerdict::Spam)
            ->and($row->spam_verdict_source)->toBe($autoVerdict);
    }

    // Green and yellow rows are acknowledged and reported; red rows only reach the digest.
    assertDelivered($row, $level !== 'red');
})->with([
    'genuine customer' => [
        [], null, 'green', 0, null, null,
    ],
    'customer abroad' => [
        ['ip' => NETWORK_FIXTURE_IP_US], null, 'green', 4, 'country_outside_shipping', null,
    ],
    'ancient browser via Tor' => [
        ['ip' => NETWORK_FIXTURE_IP_SE, 'ua' => ANCIENT_CHROME_USER_AGENT],
        fn (string $dir) => writeNetworkFeeds($dir, tor: [NETWORK_FIXTURE_IP_SE]),
        'yellow', 34, 'user_agent_ancient,tor_exit,country_outside_shipping', null,
    ],
    'Spamhaus DROP network' => [
        ['ip' => NETWORK_FIXTURE_IP_SE],
        fn (string $dir) => writeNetworkFeeds($dir, dropV4: ['89.160.20.0/24']),
        'yellow', 34, 'drop_network,country_outside_shipping', null,
    ],
    'bot: gibberish, sent at once' => [
        ['name' => 'xKqPzLmWrTvB', 'email' => 'opfer1@example.com', 'orderNumber' => '', 'subject' => 'hQwErTyUiOpAsD', 'wait' => false],
        null, 'red', 100, 'content_gibberish,fill_time_too_fast,name_without_space', null,
    ],
    'honeypot from a current browser (password manager)' => [
        ['website' => 'https://example.org', 'email' => 'kunde5@example.com'],
        null, 'red', 100, 'honeypot', null,
    ],
    'honeypot from an ancient browser (campaign pattern)' => [
        ['website' => 'https://cheap-pills.example', 'ua' => ANCIENT_CHROME_USER_AGENT, 'email' => 'opfer4@example.com'],
        null, 'red', 125, 'honeypot,user_agent_ancient', SpamVerdictSource::Auto,
    ],
    'bot: no render token, ancient Firefox, one-word name' => [
        ['name' => 'Mustermann', 'email' => 'opfer3@example.com', 'ua' => ANCIENT_FIREFOX_USER_AGENT, 'token' => false],
        null, 'red', 80, 'render_token_invalid,user_agent_ancient,name_without_space', null,
    ],
]);

it('throttles the sixth quick submit from one IP to yellow', function () {
    foreach (range(1, 6) as $i) {
        simulateSubmit(['email' => "serie{$i}@example.com", 'orderNumber' => "2000{$i}"])
            ->assertRedirect(route('withdrawal.success'));
    }

    $rows = Withdrawal::query()->orderBy('id')->get();
    expect($rows->take(5)->pluck('spam_level.value')->unique()->all())->toBe(['green'])
        ->and($rows->last()?->spam_level->value)->toBe('yellow')
        ->and($rows->last()?->spam_reason)->toBe('throttle');
});

it('flags a clean submit with an address the operator confirmed as abused', function () {
    simulateSubmit(['name' => 'xKqPzLmWrTvB', 'email' => 'opfer1@example.com', 'subject' => 'hQwErTyUiOpAsD', 'wait' => false]);
    app(MarkWithdrawalAsSpam::class)->handle(latestWithdrawal());

    $this->travel(2)->minutes();
    simulateSubmit(['email' => 'opfer1@example.com', 'orderNumber' => '10008']);

    $row = latestWithdrawal();
    expect($row->spam_level->value)->toBe('yellow')
        ->and($row->spam_score)->toBe(30)
        ->and($row->spam_reason)->toBe('abused_address');
    assertDelivered($row, true);
});

it('puts the open red and yellow submits of a simulated day into the digest, the auto-sorted one not', function () {
    writeNetworkFeeds($this->networkDir, tor: [NETWORK_FIXTURE_IP_SE]);

    simulateSubmit();
    simulateSubmit(['ip' => NETWORK_FIXTURE_IP_SE, 'ua' => ANCIENT_CHROME_USER_AGENT, 'email' => 'kunde3@example.com']);
    simulateSubmit(['website' => 'https://example.org', 'email' => 'kunde5@example.com']);
    simulateSubmit(['website' => 'https://cheap-pills.example', 'ua' => ANCIENT_CHROME_USER_AGENT, 'email' => 'opfer4@example.com']);

    $this->artisan('revoco:spam-digest')
        ->expectsOutput('Digest queued: 1 spam, 1 suspected, 1 auto-sorted.')
        ->assertSuccessful();

    $digest = Mail::queued(SpamDigest::class)->first();
    expect($digest)->not->toBeNull()
        ->and($digest->entries->pluck('email')->all())->toBe(['kunde5@example.com'])
        ->and($digest->suspected?->pluck('email')->all())->toBe(['kunde3@example.com']);
});
