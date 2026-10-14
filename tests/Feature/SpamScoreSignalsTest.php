<?php

declare(strict_types=1);

use App\Filament\Resources\WithdrawalResource\Pages\ViewWithdrawal;
use App\Mail\WithdrawalNotification;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Stop the sync-queue listeners from sending real mail while the submit path runs.
    Mail::fake();
});

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function personaPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'website' => '',
    ], $overrides);
}

it('scores a clean submit 0 / green with no reason', function () {
    submitCleanWithdrawal(personaPayload())->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', ['spam_score' => 0, 'spam_reason' => null, 'spam_level' => 'green']);
    expect(Withdrawal::sole()->spam_score)->toBe(0);
});

it('stores a rushed gibberish submit as red and still redirects', function () {
    $form = $this->withoutVite()->get(route('withdrawal.form'));
    preg_match('/name="rendered" value="([^"]*)"/', $form->getContent(), $m);

    // Posted in the same second as the render: too fast.
    $this->post(route('withdrawal.store'), personaPayload(['name' => 'xKqWnPzRtY', 'rendered' => html_entity_decode($m[1])]), ['User-Agent' => CLEAN_BROWSER_USER_AGENT])
        ->assertRedirect(route('withdrawal.success'));

    $row = Withdrawal::sole();
    expect($row->spam_level->value)->toBe('red')
        ->and($row->spam_reason)->toContain('fill_time_too_fast')->toContain('content_gibberish')
        ->and($row->spam_score)->toBeGreaterThanOrEqual(70);
});

it('classifies a submit without token and with an IE11 UA per the thresholds, still 302', function () {
    $this->post(
        route('withdrawal.store'),
        personaPayload(),
        ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko'],
    )->assertRedirect(route('withdrawal.success'));

    // render_token_invalid 40 + user_agent_ancient 25 = 65: yellow, just below red.
    $this->assertDatabaseHas('withdrawals', [
        'spam_score' => 65,
        'spam_level' => 'yellow',
        'spam_reason' => 'render_token_invalid,user_agent_ancient',
    ]);
});

it('stores a honeypot submit through the clean helper as red', function () {
    submitCleanWithdrawal(personaPayload(['website' => 'http://spam.example']))
        ->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', ['spam_score' => 100, 'spam_level' => 'red', 'spam_reason' => 'honeypot']);
});

it('keeps the original render token across a validation-error round trip', function () {
    $form = $this->withoutVite()->get(route('withdrawal.form'));
    preg_match('/name="rendered" value="([^"]*)"/', $form->getContent(), $first);

    // The first post misses the subject: back to the form with old input.
    $this->from(route('withdrawal.form'))
        ->post(route('withdrawal.store'), personaPayload(['subject' => '', 'rendered' => html_entity_decode($first[1])]))
        ->assertRedirect(route('withdrawal.form'))
        ->assertSessionHasErrors('subject');

    $this->travel(10)->seconds();

    $again = $this->withoutVite()->get(route('withdrawal.form'));
    preg_match('/name="rendered" value="([^"]*)"/', $again->getContent(), $second);
    expect($second[1])->toBe($first[1]);

    // The corrected re-submit, a few seconds after the original render: not "too fast".
    $this->post(route('withdrawal.store'), personaPayload(['rendered' => html_entity_decode($second[1])]), ['User-Agent' => CLEAN_BROWSER_USER_AGENT])
        ->assertRedirect(route('withdrawal.success'));

    $this->assertDatabaseHas('withdrawals', ['spam_score' => 0, 'spam_level' => 'green']);
});

it('applies a per-call server override without leaking it into later requests', function () {
    submitCleanWithdrawal(personaPayload(), ['HTTP_USER_AGENT' => 'MSIE 6.0']);
    submitCleanWithdrawal(personaPayload(['email' => 'zweiter@example.com']));

    expect(Withdrawal::where('email', 'kunde@example.com')->sole()->spam_reason)->toBe('user_agent_ancient')
        ->and(Withdrawal::where('email', 'zweiter@example.com')->sole()->spam_reason)->toBeNull();
});

it('never persists the IP or the user agent', function () {
    submitCleanWithdrawal(personaPayload(), ['REMOTE_ADDR' => '203.0.113.42']);

    $row = json_encode(Withdrawal::sole()->getAttributes());
    expect($row)->not->toContain('203.0.113.42')->not->toContain('Chrome/');
});

it('shows the fired signal as a German label in the merchant notification', function () {
    $row = Withdrawal::factory()->yellow()->create(['spam_reason' => 'fill_time_too_fast']);

    (new WithdrawalNotification($row))->locale('de')
        ->assertSeeInHtml('Formular unplausibel schnell ausgefüllt')
        ->assertDontSeeInHtml('fill_time_too_fast');
});

it('shows the fired signal as a German label on the Filament view page', function () {
    app()->setLocale('de');
    $this->actingAs(User::factory()->create());
    $row = Withdrawal::factory()->yellow()->create(['spam_reason' => 'fill_time_too_fast,foo_bar']);

    livewire(ViewWithdrawal::class, ['record' => $row->getKey()])
        ->assertSee('Formular unplausibel schnell ausgefüllt, foo_bar');
});

it('carries a hidden render token on the form', function () {
    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('name="rendered"', false);
});
