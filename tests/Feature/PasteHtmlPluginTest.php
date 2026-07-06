<?php

declare(strict_types=1);

use App\Filament\Pages\ManageLegal;
use App\Filament\RichContent\PasteHtmlPlugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

it('sanitizes pasted HTML: strips scripts and event handlers, keeps structure', function () {
    $dirty = '<h2>Titel</h2>'
        .'<p onclick="evil()">Text <a href="https://example.com">Link</a></p>'
        .'<script>alert(1)</script>'
        .'<ul><li>Punkt</li></ul>';

    $clean = PasteHtmlPlugin::sanitize($dirty);

    expect($clean)
        ->toContain('Titel')
        ->toContain('<h2')
        ->toContain('<ul')
        ->toContain('example.com')     // the link itself is preserved
        ->not->toContain('<script')     // script element dropped
        ->not->toContain('alert(1)')    // …including its content
        ->not->toContain('onclick');    // event handler stripped
});

it('returns an empty string for empty, whitespace, or non-string input', function () {
    expect(PasteHtmlPlugin::sanitize(null))->toBe('')
        ->and(PasteHtmlPlugin::sanitize(''))->toBe('')
        ->and(PasteHtmlPlugin::sanitize('   '))->toBe('')
        ->and(PasteHtmlPlugin::sanitize(['x']))->toBe('');
});

it('mounts the legal settings page with the paste-html plugin attached', function () {
    $this->actingAs(User::factory()->create());

    // Proves the RichEditors still build (plugin + toolbar button registered)
    // without error — the in-editor insertion itself is Phase-5 hands-on.
    livewire(ManageLegal::class)->assertSuccessful();
});
