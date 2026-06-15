<?php

declare(strict_types=1);

it('renders the neutral withdrawal form', function () {
    $response = $this->withoutVite()->get(route('withdrawal.form'));

    $response->assertOk();

    // The four fields from the prototype, by their submit name.
    $response->assertSee('name="name"', false);
    $response->assertSee('name="email"', false);
    $response->assertSee('name="orderNumber"', false);
    $response->assertSee('name="subject"', false);

    // Translated copy is rendered — not the raw translation keys.
    $response->assertSee('Ihr Vor- und Nachname');
    $response->assertSee('E-Mail-Adresse');
    $response->assertSee('Bestellnummer / Vertragsnummer');
    $response->assertSee('Pflichtfeld');
    $response->assertDontSee('wf.');

    // Honeypot field is present in the markup.
    $response->assertSee('name="website"', false);

    // Neutral theme applied by default.
    $response->assertSee('data-theme="neutral"', false);
});

it('exposes a named store route for the form action', function () {
    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertSee('action="'.route('withdrawal.store').'"', false);
});

it('answers the store stub with 501 until slice-003 implements it', function () {
    // Placeholder contract: the named route resolves, the handler is not yet built.
    $this->post(route('withdrawal.store'))->assertStatus(501);
});

it('applies the configured theme to the form card', function () {
    // config('revoco.theme') is backed by env('APP_THEME'); overriding the
    // resolved config value exercises the same data-theme mechanism.
    config()->set('revoco.theme', 'foo');

    $this->withoutVite()->get(route('withdrawal.form'))
        ->assertOk()
        ->assertSee('data-theme="foo"', false);
});
