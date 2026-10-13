<?php

declare(strict_types=1);

use App\Filament\Pages\ManageNotifications;
use App\Mail\SpamDigest;
use App\Models\User;
use App\Models\Withdrawal;
use App\Settings\NotificationSettings;
use App\Spam\SpamDigestSchedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    config(['revoco.merchant_email' => 'merchant@example.com']);
    $this->travelTo(Carbon::create(2026, 10, 14, 7, 0, 0, 'Europe/Berlin'));
});

/** The digest event as registered on a fresh schedule. */
function digestEvent(): Event
{
    $schedule = new Schedule(config('app.timezone'));
    SpamDigestSchedule::register($schedule);

    $events = array_values(array_filter(
        $schedule->events(),
        fn (Event $event) => str_contains((string) $event->command, 'revoco:spam-digest'),
    ));

    expect($events)->toHaveCount(1);

    return $events[0];
}

it('queues one digest listing exactly the unresolved red rows of the last 24 hours', function () {
    Withdrawal::factory()->spam()->count(3)->create(['created_at' => now()->subHours(5)]);
    Withdrawal::factory()->spam()->create(['created_at' => now()->subHours(30)]);
    Withdrawal::factory()->spam()->notSpam()->create(['created_at' => now()->subHours(2)]);
    Withdrawal::factory()->confirmedSpam()->create(['created_at' => now()->subHours(2)]);
    Withdrawal::factory()->yellow()->create(['created_at' => now()->subHours(2)]);
    Withdrawal::factory()->create(['created_at' => now()->subHours(2)]);

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    Mail::assertQueued(SpamDigest::class, 1);
    Mail::assertQueued(SpamDigest::class, function (SpamDigest $mail) {
        return $mail->hasTo('merchant@example.com')
            && $mail->entries->count() === 3
            && $mail->total === 3;
    });
});

it('lists name, e-mail, reason and a not-spam link per entry', function () {
    $row = Withdrawal::factory()->spam()->create([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'spam_reason' => 'honeypot',
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    $queued = Mail::queued(SpamDigest::class)->first();
    expect($queued)->not->toBeNull();
    $html = $queued->locale('de')->render();

    expect($html)->toContain('Max Mustermann')
        ->toContain('kunde@example.com')
        ->toContain('honeypot')
        ->toContain('/spam/'.$row->id.'/not-spam?')
        ->toContain('tab=spam')
        ->not->toContain('weitere');
});

it('uses the singular for a single entry', function () {
    Withdrawal::factory()->spam()->create(['created_at' => now()->subHour()]);

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    $queued = Mail::queued(SpamDigest::class)->first();
    expect($queued)->not->toBeNull();
    $queued->locale('de');
    expect($queued->envelope()->subject)->toContain('1 Eingang in')
        ->and($queued->render())->toContain('ist 1 Widerruf als Spam');
});

it('lists 50 entries and counts the rest', function () {
    Withdrawal::factory()->spam()->count(60)->create(['created_at' => now()->subHour()]);

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    $queued = Mail::queued(SpamDigest::class)->first();
    expect($queued)->not->toBeNull()
        ->and($queued->entries)->toHaveCount(50)
        ->and($queued->total)->toBe(60);
    expect($queued->locale('de')->render())->toContain('… und 10 weitere');
});

it('sends nothing, and says why, with no rows or no recipient', function () {
    $this->artisan('revoco:spam-digest')
        ->expectsOutput('No digest sent: no unresolved spam in the last 24 hours.')
        ->assertSuccessful();

    Withdrawal::factory()->spam()->create(['created_at' => now()->subHour()]);
    config(['revoco.merchant_email' => null]);

    $this->artisan('revoco:spam-digest', ['--no-interaction' => true])
        ->expectsOutput('No digest sent: no notification recipient is configured.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

it('registers the schedule at the configured time in the app timezone', function () {
    $event = digestEvent();
    expect($event->expression)->toBe('0 7 * * *')
        ->and((string) $event->timezone)->toBe(config('app.timezone'));

    $settings = app(NotificationSettings::class);
    $settings->spam_digest_time = '08:30';
    $settings->save();

    expect(digestEvent()->expression)->toBe('30 8 * * *');
});

it('falls back to 07:00 when the settings cannot be read', function () {
    Schema::drop('settings');

    expect(SpamDigestSchedule::time())->toBe('07:00')
        ->and(digestEvent()->expression)->toBe('0 7 * * *');
});

it('validates and saves the digest time on the notifications page', function () {
    $this->actingAs(User::factory()->create());

    livewire(ManageNotifications::class)
        ->fillForm(['spam_digest_time' => '25:99'])
        ->call('save')
        ->assertHasFormErrors(['spam_digest_time']);

    livewire(ManageNotifications::class)
        ->fillForm(['spam_digest_time' => '06:15'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(NotificationSettings::class)->spam_digest_time)->toBe('06:15');
});
