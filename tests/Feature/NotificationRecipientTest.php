<?php

declare(strict_types=1);

use App\Filament\Pages\ManageNotifications;
use App\Mail\WithdrawalNotification;
use App\Models\User;
use App\Models\Withdrawal;
use App\Settings\LegalSettings;
use App\Settings\NotificationSettings;
use App\Support\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

function setPanelRecipient(?string $email): void
{
    $settings = app(NotificationSettings::class);
    $settings->notification_email = $email;
    $settings->save();
}

function setImprintEmail(?string $email): void
{
    $settings = app(LegalSettings::class);
    $settings->imprint_email = $email;
    $settings->save();
}

// ---------------------------------------------------------------------------
// Recipient resolution — precedence: panel → env → imprint → none
// ---------------------------------------------------------------------------

it('prefers the panel setting over env and imprint', function () {
    setPanelRecipient('panel@example.com');
    config(['revoco.merchant_email' => 'env@example.com']);
    setImprintEmail('imprint@example.com');

    expect(NotificationRecipient::resolve())->toBe('panel@example.com');
});

it('falls back to the env override when the panel setting is empty', function () {
    setPanelRecipient(null);
    config(['revoco.merchant_email' => 'env@example.com']);
    setImprintEmail('imprint@example.com');

    expect(NotificationRecipient::resolve())->toBe('env@example.com');
});

it('falls back to the imprint e-mail when panel and env are empty', function () {
    setPanelRecipient(null);
    config(['revoco.merchant_email' => null]);
    setImprintEmail('imprint@example.com');

    expect(NotificationRecipient::resolve())->toBe('imprint@example.com');
});

it('resolves to null when nothing is configured', function () {
    setPanelRecipient(null);
    config(['revoco.merchant_email' => null]);
    setImprintEmail(null);

    expect(NotificationRecipient::resolve())->toBeNull();
});

it('treats a whitespace-only panel value as unset', function () {
    setPanelRecipient('   ');
    config(['revoco.merchant_email' => 'env@example.com']);

    expect(NotificationRecipient::resolve())->toBe('env@example.com');
});

// ---------------------------------------------------------------------------
// Listener routing — the notification follows the resolved recipient
// ---------------------------------------------------------------------------

it('routes the notification to the panel recipient over env', function () {
    Mail::fake();
    setPanelRecipient('panel@example.com');
    config(['revoco.merchant_email' => 'env@example.com']);

    $this->post(route('withdrawal.store'), [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'A-1',
        'subject' => 'Ware',
        'website' => '',
    ]);

    Mail::assertQueued(WithdrawalNotification::class, fn ($m) => $m->hasTo('panel@example.com'));
});

it('routes the notification to the imprint e-mail when it is the only address', function () {
    Mail::fake();
    setPanelRecipient(null);
    config(['revoco.merchant_email' => null]);
    setImprintEmail('imprint@example.com');

    $this->post(route('withdrawal.store'), [
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'orderNumber' => 'A-1',
        'subject' => 'Ware',
        'website' => '',
    ]);

    Mail::assertQueued(WithdrawalNotification::class, fn ($m) => $m->hasTo('imprint@example.com'));
});

// ---------------------------------------------------------------------------
// Spam prominence — the verdict leads the subject line
// ---------------------------------------------------------------------------

it('leads the notification subject with the spam verdict when flagged', function () {
    $clean = (new WithdrawalNotification(
        Withdrawal::factory()->create(['spam' => false])
    ))->locale('de');
    $clean->assertHasSubject('Neuer Widerruf eingegangen');

    $flagged = (new WithdrawalNotification(
        Withdrawal::factory()->create(['spam' => true, 'spam_reason' => 'honeypot'])
    ))->locale('de');
    $flagged->assertHasSubject('⚠ SPAM-VERDACHT: Neuer Widerruf eingegangen');
});

// ---------------------------------------------------------------------------
// Test-mail action on the ManageNotifications page
// ---------------------------------------------------------------------------

it('sends a test notification to the resolved recipient', function () {
    Mail::fake();
    setPanelRecipient('ops@example.com');
    $this->actingAs(User::factory()->create());

    livewire(ManageNotifications::class)->callAction('sendTest');

    Mail::assertSent(WithdrawalNotification::class, fn ($m) => $m->hasTo('ops@example.com'));
});

it('sends no test mail when no recipient is configured', function () {
    Mail::fake();
    setPanelRecipient(null);
    config(['revoco.merchant_email' => null]);
    setImprintEmail(null);
    $this->actingAs(User::factory()->create());

    livewire(ManageNotifications::class)->callAction('sendTest');

    Mail::assertNothingSent();
});
