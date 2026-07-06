<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('brands the admin login page as Revoco with logo and favicon', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    // Brand name (also drives the page <title>) — present, and the "Laravel"
    // fallback is gone.
    $response->assertSee('Revoco', false);
    $response->assertDontSee('<title>Laravel</title>', false);
    // Brand logo + favicon assets are wired in the head/brand area.
    $response->assertSee('img/revoco-logo.svg', false);
    $response->assertSee('img/revoco-favicon.svg', false);
});

it('brands an authenticated panel page as Revoco', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee('Revoco', false)
        ->assertSee('img/revoco-logo.svg', false);
});

it('ships the brand assets in public/img', function () {
    expect(file_exists(public_path('img/revoco-logo.svg')))->toBeTrue()
        ->and(file_exists(public_path('img/revoco-logo-dark.svg')))->toBeTrue()
        ->and(file_exists(public_path('img/revoco-favicon.svg')))->toBeTrue();
});
