<?php

declare(strict_types=1);

use App\Jobs\SendNtfyPush;
use App\Mail\WithdrawalAcknowledgment;
use App\Mail\WithdrawalNotification;
use App\Models\Withdrawal;
use App\Services\Ntfy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Consumer language switcher — end-to-end from the switcher choice (locale
// cookie) through the form, the submit, the stored locale and the delivery
// languages.
// ---------------------------------------------------------------------------

it('hides the switcher when only one locale is available', function () {
    config(['app.available_locales' => ['de']]);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        // The .wf-lang CSS is always inlined; assert the nav markup is absent.
        ->assertDontSee('<nav class="wf-langs"', false)
        ->assertDontSee(route('locale.set', 'en'), false);
});

it('renders the switcher with the available locales and a11y attributes', function () {
    config(['app.available_locales' => ['de', 'en']]);

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('<nav class="wf-langs"', false)
        ->assertSee('<a class="wf-flag"', false)       // en renders as a flag link
        ->assertSee('viewBox="0 0 24 16"', false)      // flag SVG is inlined
        ->assertSee('Deutsch')                         // autonym kept on aria-label/title
        ->assertSee('English')
        ->assertSee(route('locale.set', 'en'), false) // link to switch to en
        ->assertSee('hreflang="en"', false)
        ->assertSee('aria-current', false);            // active locale (de) marked
});

it('renders the form in English when the locale cookie selects en', function () {
    config(['app.available_locales' => ['de', 'en']]);

    $this->withUnencryptedCookie('locale', 'en')
        ->withoutVite()
        ->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('Withdrawal Form')
        ->assertSee('Confirm withdrawal')
        ->assertDontSee('Widerrufsformular')
        ->assertDontSee('wf.');
});

it('persists the en locale on submit and routes delivery languages correctly', function () {
    Mail::fake();
    config(['app.available_locales' => ['de', 'en']]);
    config(['revoco.merchant_email' => 'merchant@example.com']);

    $this->withUnencryptedCookie('locale', 'en')
        ->post(route('withdrawal.store'), [
            'name' => 'Max Mustermann',
            'email' => 'kunde@example.com',
            'orderNumber' => 'A-12345',
            'subject' => 'Inflatable boat model X',
            'website' => '',
        ])
        ->assertRedirect(route('withdrawal.success'));

    expect(Withdrawal::firstOrFail()->locale)->toBe('en');

    // Consumer acknowledgment follows the consumer's chosen locale...
    Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($m) => $m->locale === 'en');
    // ...the merchant notification stays on the frozen default ('de'), NOT the
    // consumer's 'en' (which app()->setLocale() pushed into config('app.locale')).
    expect(config('app.default_locale'))->toBe('de');
    Mail::assertQueued(WithdrawalNotification::class, fn ($m) => $m->locale === 'de');
});

it('renders the acknowledgment e-mail in English for an en withdrawal', function () {
    $withdrawal = Withdrawal::create([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'order_number' => 'A-12345',
        'subject' => 'Inflatable boat model X',
        'locale' => 'en',
        'spam' => false,
        'spam_reason' => null,
    ]);

    $mailable = (new WithdrawalAcknowledgment($withdrawal))->locale('en');

    $mailable->assertSeeInHtml('Receipt of your withdrawal confirmed');
    $mailable->assertSeeInHtml('Your details');
    $mailable->assertSeeInHtml('Inflatable boat model X');
    $mailable->assertSeeInHtml('Europe/Berlin');
    $mailable->assertDontSeeInHtml('Eingang Ihres Widerrufs bestätigt');
});

it('pins the operator ntfy push to the app default locale regardless of the active locale', function () {
    // Make the pin observable: register an en push translation that would only be
    // used if the job followed the runtime locale instead of the app default.
    app('translator')->addLines(['push.title' => 'EN PUSH TITLE'], 'en');
    app()->setLocale('en'); // consumer chose English

    config(['revoco.ntfy.server' => 'https://ntfy.example.test', 'revoco.ntfy.topic' => 'topic']);
    Http::fake(['ntfy.example.test/*' => Http::response('', 200)]);

    (new SendNtfyPush(spam: false))->handle(new Ntfy);

    // The push title is the German default, not the en override.
    Http::assertSent(fn ($request) => $request->hasHeader('Title', __('push.title', [], 'de'))
        && ! $request->hasHeader('Title', 'EN PUSH TITLE'));
});

it('ignores a cross-origin referer when redirecting back', function () {
    config(['app.available_locales' => ['de', 'en']]);

    // A forged Referer must not become an open redirect — fall back to the form.
    $this->get(route('locale.set', 'en'), ['referer' => 'https://evil.example/phish'])
        ->assertRedirect(route('withdrawal.form'));
});

it('sets a locale cookie for a supported language and redirects back', function () {
    config(['app.available_locales' => ['de', 'en']]);

    $this->get(route('locale.set', 'en'), ['referer' => route('withdrawal.form')])
        ->assertRedirect(route('withdrawal.form'))
        ->assertCookie('locale', 'en', false); // unencrypted preference cookie
});

it('ignores an unsupported language and writes no cookie', function () {
    config(['app.available_locales' => ['de', 'en']]);

    $this->get(route('locale.set', 'fr'), ['referer' => route('withdrawal.form')])
        ->assertRedirect(route('withdrawal.form'))
        ->assertCookieMissing('locale');
});
