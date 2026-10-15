<?php

declare(strict_types=1);

use App\Filament\Resources\WithdrawalResource;
use App\Models\User;
use App\Services\ReleaseChecker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

const RELEASES_LATEST_URL = 'https://api.github.com/repos/ChriRas/revoco/releases/latest';

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['revoco.version' => '1.1.0', 'revoco.update_check.enabled' => true, 'operator.locale' => 'en']);
    Cache::flush();
});

/** Fakes the GitHub `releases/latest` answer. */
function fakeLatestRelease(string $tag = 'v1.2.0', bool $prerelease = false): void
{
    Http::fake([RELEASES_LATEST_URL => Http::response(['tag_name' => $tag, 'prerelease' => $prerelease, 'draft' => false])]);
}

/** @return array{version: string, tag: string}|null */
function storedRelease(): ?array
{
    $value = Cache::get(ReleaseChecker::CACHE_KEY);

    return is_array($value) ? $value : null;
}

function cacheRelease(string $version): void
{
    Cache::forever(ReleaseChecker::CACHE_KEY, ['version' => $version, 'tag' => 'v'.$version]);
}

// ---------------------------------------------------------------------------
// revoco:release-check
// ---------------------------------------------------------------------------

it('stores the latest stable release with one anonymous request', function () {
    fakeLatestRelease();

    $this->artisan('revoco:release-check', ['--no-interaction' => true])->assertSuccessful();

    expect(storedRelease())->toBe(['version' => '1.2.0', 'tag' => 'v1.2.0']);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'GET'
            && $request->url() === RELEASES_LATEST_URL
            && $request->body() === ''
            && ! $request->hasHeader('Authorization')
            && ! str_contains(json_encode($request->headers(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), (string) config('app.url'))
            && ! str_contains(json_encode($request->headers(), JSON_THROW_ON_ERROR), (string) config('revoco.version'));
    });
});

it('makes no request and stores nothing when the check is opted out', function () {
    config(['revoco.update_check.enabled' => false]);
    Http::fake();

    $this->artisan('revoco:release-check', ['--no-interaction' => true])
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();

    Http::assertNothingSent();
    expect(storedRelease())->toBeNull();
});

it('keeps the previous result and fails when the answer is unusable', function (string $case, callable $fake) {
    cacheRelease('1.0.3');
    $fake();
    Log::spy();

    $this->artisan('revoco:release-check', ['--no-interaction' => true])->assertFailed();

    Log::shouldHaveReceived('warning')->once();

    expect(storedRelease())->toBe(['version' => '1.0.3', 'tag' => 'v1.0.3']);
})->with([
    'non-stable tag' => ['rc', fn () => fakeLatestRelease('v1.3.0-rc.1')],
    'prerelease flag' => ['pre', fn () => fakeLatestRelease('v1.3.0', prerelease: true)],
    'malformed json' => ['json', fn () => Http::fake([RELEASES_LATEST_URL => Http::response('not json {')])],
    'http 500' => ['500', fn () => Http::fake([RELEASES_LATEST_URL => Http::response('', 500)])],
    'connection error' => ['conn', fn () => Http::fake([RELEASES_LATEST_URL => fn () => throw new ConnectionException('boom')])],
]);

it('schedules the release check daily at 04:45 without overlap', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'revoco:release-check'));

    expect($event->expression)->toBe('45 4 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('falls back to dev when REVOCO_VERSION is unset', function () {
    $config = require config_path('revoco.php');

    expect($config['version'])->toBe('dev')
        ->and($config['update_check']['enabled'])->toBeTrue()
        ->and($config['update_check']['repository'])->toBe('ChriRas/revoco');
})->skip(fn () => env('REVOCO_VERSION') !== null, 'REVOCO_VERSION is set in this environment');

// ---------------------------------------------------------------------------
// Panel display
// ---------------------------------------------------------------------------

it('shows the version and a badge linking to the release notes when a newer release is cached', function () {
    cacheRelease('1.2.0');

    $this->actingAs(User::factory()->create())
        ->get(WithdrawalResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Revoco 1.1.0')
        ->assertSee('1.2.0 available')
        ->assertSee('href="https://github.com/ChriRas/revoco/releases/tag/v1.2.0"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false);

    Http::assertNothingSent();
});

it('shows no badge unless the cached release is newer and the check is on', function (string $running, ?string $cached, bool $enabled) {
    config(['revoco.version' => $running, 'revoco.update_check.enabled' => $enabled]);

    if ($cached !== null) {
        cacheRelease($cached);
    }

    $this->actingAs(User::factory()->create())
        ->get(WithdrawalResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Revoco '.$running)
        ->assertDontSee('releases/tag/', false);

    Http::assertNothingSent();
})->with([
    'same version' => ['1.1.0', '1.1.0', true],
    'older release' => ['1.1.0', '1.0.3', true],
    'nothing cached' => ['1.1.0', null, true],
    'opted out' => ['1.1.0', '1.2.0', false],
    'dev build' => ['dev', '1.2.0', true],
]);

it('flags a release candidate once the matching stable release is out', function () {
    config(['revoco.version' => '1.2.0-rc.1']);
    cacheRelease('1.2.0');

    $this->actingAs(User::factory()->create())
        ->get(WithdrawalResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Revoco 1.2.0-rc.1')
        ->assertSee('1.2.0 available');
});

it('renders the badge in the backend locale', function () {
    config(['operator.locale' => 'de']);
    cacheRelease('1.2.0');

    $this->actingAs(User::factory()->create())
        ->get(WithdrawalResource::getUrl('index'))
        ->assertOk()
        ->assertSee('1.2.0 verfügbar');
});

it('shows no version on the login page', function () {
    cacheRelease('1.2.0');

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('Revoco 1.1.0')
        ->assertDontSee('releases/tag/', false);
});
