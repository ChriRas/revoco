<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('injects the sticky rich-editor toolbar CSS into panel pages', function () {
    // The HEAD_END render hook applies panel-wide, so any authenticated panel page
    // carries the rule. The stickiness itself is visual → verified hands-on.
    $this->actingAs(User::factory()->create());

    $this->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee('.fi-fo-rich-editor-toolbar', false)
        ->assertSee('position: sticky', false);
});

it('does not leak the toolbar CSS onto the public consumer form', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('fi-fo-rich-editor-toolbar', false);
});
