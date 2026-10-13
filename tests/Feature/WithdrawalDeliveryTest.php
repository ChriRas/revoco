<?php

declare(strict_types=1);

use App\Mail\WithdrawalAcknowledgment;
use App\Mail\WithdrawalNotification;
use App\Models\Withdrawal;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function deliveryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Erika Musterfrau',
        'email' => 'erika@example.com',
        'orderNumber' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'website' => '',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeWithdrawal(array $overrides = []): Withdrawal
{
    return Withdrawal::create(array_merge([
        'name' => 'Erika Musterfrau',
        'email' => 'erika@example.com',
        'order_number' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'locale' => 'de',
        'spam_level' => 'green',
        'spam_reason' => null,
    ], $overrides));
}

it('defers all delivery to the queue so the submit is never blocked', function () {
    // With the queue faked, the queued listener is recorded but never executed —
    // so SMTP/push cannot run in-request and cannot affect the response.
    Queue::fake();

    submitCleanWithdrawal(deliveryPayload())
        ->assertRedirect(route('withdrawal.success'));

    // Stored regardless of any delivery outcome, and delivery was queued, not inline.
    $this->assertDatabaseHas('withdrawals', ['email' => 'erika@example.com']);
    Queue::assertPushed(CallQueuedListener::class);
});

it('queues the consumer acknowledgment and merchant notification on submit', function () {
    Mail::fake();
    config(['revoco.merchant_email' => 'merchant@example.com']);

    submitCleanWithdrawal(deliveryPayload());

    Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($m) => $m->hasTo('erika@example.com'));
    Mail::assertQueued(WithdrawalNotification::class, fn ($m) => $m->hasTo('merchant@example.com'));
    // Exactly one of each — guards against a double-firing listener.
    Mail::assertQueuedCount(2);
    // Delivery is queued, never sent inline on the request.
    Mail::assertNothingSent();
});

it('sends neither acknowledgment nor merchant mail for a honeypot-flagged submit', function () {
    Mail::fake();
    config(['revoco.merchant_email' => 'merchant@example.com']);

    submitCleanWithdrawal(deliveryPayload(['website' => 'http://spam.example']))
        ->assertRedirect(route('withdrawal.success'));

    // Stored as proof regardless — a red row only shows up in the morning digest.
    $this->assertDatabaseHas('withdrawals', ['email' => 'erika@example.com', 'spam_level' => 'red']);
    Mail::assertNotQueued(WithdrawalAcknowledgment::class);
    Mail::assertNotQueued(WithdrawalNotification::class);
    Mail::assertNothingQueued();
});

it('still delivers a throttle-flagged submit: throttle alone is yellow, not red', function () {
    Mail::fake();
    config(['revoco.merchant_email' => 'merchant@example.com']);

    // Five clean submits from one IP, the sixth trips the soft rate-limit signal.
    foreach (range(1, 6) as $i) {
        submitCleanWithdrawal(deliveryPayload(['email' => "kunde{$i}@example.com"]));
    }

    $this->assertDatabaseHas('withdrawals', [
        'email' => 'kunde6@example.com',
        'spam_level' => 'yellow',
        'spam_reason' => 'throttle',
    ]);
    // A yellow row is delivered like a green one (the merchant mail carries the warning).
    Mail::assertQueued(WithdrawalAcknowledgment::class, 6);
    Mail::assertQueued(WithdrawalNotification::class, 6);
    Mail::assertQueued(WithdrawalNotification::class, fn ($m) => $m->withdrawal->email === 'kunde6@example.com');
});

it('skips the merchant notification when no operator address is configured', function () {
    Mail::fake();
    config(['revoco.merchant_email' => null]);

    submitCleanWithdrawal(deliveryPayload());

    Mail::assertQueued(WithdrawalAcknowledgment::class);
    Mail::assertNotQueued(WithdrawalNotification::class);
});

it('renders the § 356a acknowledgment with receipt confirmation, content and time — no advertising', function () {
    $withdrawal = makeWithdrawal();

    $mailable = (new WithdrawalAcknowledgment($withdrawal))->locale('de');

    $mailable->assertSeeInHtml('Eingang Ihres Widerrufs bestätigt');
    $mailable->assertSeeInHtml('Schlauchboot Modell X');
    $mailable->assertSeeInHtml('A-12345');
    $mailable->assertSeeInHtml($withdrawal->created_at->format('d.m.Y'));
    // The IANA identifier is no longer shown — the timezone now renders as the
    // MEZ/MESZ abbreviation (exact label asserted in the DST-aware test below).
    $mailable->assertDontSeeInHtml('Europe/Berlin');
    // No advertising in the acknowledgment (§ 356a Abs. 4).
    $mailable->assertDontSeeInHtml('Rabatt');
    $mailable->assertDontSeeInHtml('Gutschein');
    $mailable->assertDontSeeInHtml('Newsletter');
    $mailable->assertDontSeeInHtml('Angebot');
});

it('formats the acknowledgment date and timezone per consumer locale (DST-aware)', function () {
    // Summer: Europe/Berlin is on CEST, i.e. German MESZ.
    $this->travelTo(Carbon::create(2026, 6, 27, 14, 30, 0, 'Europe/Berlin'));
    $summer = makeWithdrawal();

    (new WithdrawalAcknowledgment($summer))->locale('de')
        ->assertSeeInHtml('27.06.2026, 14:30 Uhr (MESZ)');

    $en = (new WithdrawalAcknowledgment($summer))->locale('en');
    $en->assertSeeInHtml('Jun 27, 2026, 14:30');
    $en->assertSeeInHtml('CEST');
    $en->assertDontSeeInHtml('27.06.2026');     // no German date notation in the English mail
    $en->assertDontSeeInHtml('Europe/Berlin');  // IANA id replaced by the abbreviation
    $en->assertDontSeeInHtml('MESZ');           // German abbreviation absent in the English mail

    // Winter: the same code path yields CET / MEZ — the label tracks the timestamp.
    $this->travelTo(Carbon::create(2026, 1, 15, 9, 5, 0, 'Europe/Berlin'));
    $winter = makeWithdrawal();
    (new WithdrawalAcknowledgment($winter))->locale('de')->assertSeeInHtml('(MEZ)');
    (new WithdrawalAcknowledgment($winter))->locale('en')->assertSeeInHtml('CET');

    $this->travelBack();
});

it('renders the de-pinned timezone label in the operator notification', function () {
    // The operator notification gained the timezone label in this slice. It is
    // pinned to the default locale, so it always shows the German MEZ/MESZ — never
    // a consumer's CET/CEST — regardless of who triggered the withdrawal.
    $this->travelTo(Carbon::create(2026, 6, 27, 14, 30, 0, 'Europe/Berlin'));

    (new WithdrawalNotification(makeWithdrawal()))->locale('de')
        ->assertSeeInHtml('27.06.2026, 14:30 Uhr (MESZ)');

    $this->travelBack();
});

it('reflects the spam status in the merchant notification', function () {
    $clean = (new WithdrawalNotification(makeWithdrawal(['spam_level' => 'green'])))->locale('de');
    $clean->assertSeeInHtml('unauffällig');

    $flagged = (new WithdrawalNotification(makeWithdrawal(['spam_level' => 'yellow', 'spam_reason' => 'honeypot'])))->locale('de');
    $flagged->assertSeeInHtml('Spam-Verdacht');
    $flagged->assertSeeInHtml('Verstecktes Honeypot-Feld ausgefüllt');
});

it('publishes a PII-free ntfy push when enabled', function () {
    Mail::fake();
    Http::fake(['ntfy.example.test/*' => Http::response('', 200)]);
    config([
        'revoco.ntfy.enabled' => true,
        'revoco.ntfy.server' => 'https://ntfy.example.test',
        'revoco.ntfy.topic' => 'revoco-secret-topic',
        'revoco.ntfy.token' => null,
    ]);

    submitCleanWithdrawal(deliveryPayload([
        'name' => 'Geheim Nachname',
        'email' => 'geheim@example.com',
        'orderNumber' => 'ORDER-XYZ',
    ]));

    Http::assertSent(function ($request) {
        // Check the whole request (body + all headers like Title/Tags) for any PII.
        $payload = $request->body().json_encode($request->headers());

        return str_contains($request->url(), 'ntfy.example.test/revoco-secret-topic')
            && ! str_contains($payload, 'Geheim')
            && ! str_contains($payload, 'geheim@example.com')
            && ! str_contains($payload, 'ORDER-XYZ')
            && ! str_contains($payload, 'Schlauchboot');
    });
});

it('sends no push when ntfy is disabled', function () {
    Mail::fake();
    Http::fake();
    config(['revoco.ntfy.enabled' => false]);

    submitCleanWithdrawal(deliveryPayload());

    Http::assertNothingSent();
});
