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
    // Yesterday's digest went out: this run covers the last 24 hours.
    lastDigestAt(now()->subDay());
});

/** Records when the previous digest was queued; null = none yet. */
function lastDigestAt(?Carbon $at): void
{
    $settings = app(NotificationSettings::class);
    $settings->spam_digest_last_sent_at = $at?->toIso8601String();
    $settings->save();
}

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
        ->toContain('Verstecktes Honeypot-Feld ausgefüllt')
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
    expect($queued->envelope()->subject)->toContain('1 Eingang seit')
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
        ->expectsOutput('No digest sent: nothing to report since the last digest.')
        ->assertSuccessful();

    Withdrawal::factory()->spam()->create(['created_at' => now()->subHour()]);
    config(['revoco.merchant_email' => null]);

    $this->artisan('revoco:spam-digest', ['--no-interaction' => true])
        ->expectsOutput('No digest sent: no notification recipient is configured.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

it('lists unjudged yellow rows of the last 24 hours in a separate section with a mark-as-spam link', function () {
    $red = Withdrawal::factory()->spam()->create(['created_at' => now()->subHours(3)]);
    $yellow = Withdrawal::factory()->yellow()->create([
        'name' => 'Erika Mustermann',
        'email' => 'kunde2@example.com',
        'spam_reason' => 'user_agent_ancient',
        'created_at' => now()->subHours(2),
    ]);
    Withdrawal::factory()->yellow()->create(['created_at' => now()->subHours(30)]);
    Withdrawal::factory()->yellow()->notSpam()->create(['created_at' => now()->subHours(2)]);
    Withdrawal::factory()->yellow()->create(['spam_verdict' => 'spam', 'spam_verdict_at' => now(), 'created_at' => now()->subHours(2)]);

    $this->artisan('revoco:spam-digest')
        ->expectsOutput('Digest queued: 1 spam, 1 suspected, 0 auto-sorted.')
        ->assertSuccessful();

    $queued = Mail::queued(SpamDigest::class)->first();
    expect($queued)->not->toBeNull()
        ->and($queued->entries->pluck('id')->all())->toBe([$red->id])
        ->and($queued->suspected?->pluck('id')->all())->toBe([$yellow->id])
        ->and($queued->suspectedTotal)->toBe(1);

    $queued->locale('de');
    $html = $queued->render();
    $suspectedSection = (string) strstr($html, 'Verdächtig');

    expect($queued->envelope()->subject)->toContain('2 Eingänge')
        ->and($html)->toContain('Als Spam eingestuft')
        ->and($html)->toContain('/spam/'.$red->id.'/not-spam?')
        ->and($suspectedSection)->toContain('Erika Mustermann')
        ->toContain('kunde2@example.com')
        ->toContain('ist 1 Widerruf als verdächtig')
        ->toContain('/spam/'.$yellow->id.'/spam?')
        ->toContain('Als Spam markieren')
        ->not->toContain('/spam/'.$yellow->id.'/not-spam?');
});

it('sends the digest when only yellow rows exist, without the spam section, linking the inbox', function () {
    Withdrawal::factory()->yellow()->count(2)->create(['created_at' => now()->subHour()]);

    $this->artisan('revoco:spam-digest')
        ->expectsOutput('Digest queued: 0 spam, 2 suspected, 0 auto-sorted.')
        ->assertSuccessful();

    $queued = Mail::queued(SpamDigest::class)->first();
    expect($queued)->not->toBeNull();
    $html = $queued->locale('de')->render();

    expect($html)->toContain('sind 2 Widerrufe als verdächtig')
        ->not->toContain('Als Spam eingestuft')
        ->not->toContain('tab=spam');
});

it('counts auto-sorted rows with their purge date instead of listing them, and sends for them alone', function () {
    Withdrawal::factory()->autoSorted(now()->subHours(5))->create(['created_at' => now()->subHours(5)]);
    Withdrawal::factory()->autoSorted(now()->subHours(2))->create(['created_at' => now()->subHours(2)]);

    $this->artisan('revoco:spam-digest')
        ->expectsOutput('Digest queued: 0 spam, 0 suspected, 2 auto-sorted.')
        ->assertSuccessful();

    $queued = Mail::queued(SpamDigest::class)->first();
    expect($queued)->not->toBeNull()
        ->and($queued->entries)->toHaveCount(0)
        ->and($queued->autoSortedTotal)->toBe(2);

    // The first of them was judged 5 hours ago (14.10. 02:00) and is purged 7 days later.
    expect($queued->locale('de')->render())->toContain('2 Eingänge wurden automatisch als Spam aussortiert')
        ->toContain('ab dem 21.10.2026 gelöscht')
        ->toContain('Im Spam-Reiter prüfen')
        ->toContain('tab=spam');
});

it('leaves the count line out without auto-sorted rows', function () {
    Withdrawal::factory()->spam()->create(['created_at' => now()->subHour()]);

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    expect(Mail::queued(SpamDigest::class)->first()?->locale('de')->render())->not->toContain('automatisch als Spam aussortiert');
});

it('covers everything since the last queued digest, so a skipped run loses nothing', function () {
    lastDigestAt(now()->subDays(3));
    $threeDays = Withdrawal::factory()->spam()->create(['created_at' => now()->subDays(2)->subHours(20)]);
    Withdrawal::factory()->spam()->create(['created_at' => now()->subDays(4)]);

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    expect(Mail::queued(SpamDigest::class)->first()?->entries->pluck('id')->all())->toBe([$threeDays->id])
        ->and(app(NotificationSettings::class)->spam_digest_last_sent_at)->toBe(now()->toIso8601String());
});

it('looks back at most 7 days, also on the very first run', function () {
    lastDigestAt(null);
    $sixDays = Withdrawal::factory()->spam()->create(['created_at' => now()->subDays(6)]);
    Withdrawal::factory()->spam()->create(['created_at' => now()->subDays(8)]);

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    expect(Mail::queued(SpamDigest::class)->first()?->entries->pluck('id')->all())->toBe([$sixDays->id]);

    lastDigestAt(now()->subDays(30));
    Mail::fake();

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    expect(Mail::queued(SpamDigest::class)->first()?->entries->pluck('id')->all())->toBe([$sixDays->id]);
});

it('keeps the window open when there is nothing to report', function () {
    $this->artisan('revoco:spam-digest')->expectsOutput('No digest sent: nothing to report since the last digest.');
    expect(app(NotificationSettings::class)->spam_digest_last_sent_at)->toBe(now()->subDay()->toIso8601String());

    $this->travel(1)->day();
    $row = Withdrawal::factory()->spam()->create(['created_at' => now()->subHours(30)]);

    $this->artisan('revoco:spam-digest')->assertSuccessful();

    expect(Mail::queued(SpamDigest::class)->first()?->entries->pluck('id')->all())->toBe([$row->id]);
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
