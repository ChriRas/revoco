<?php

declare(strict_types=1);

use App\Support\LegalPageResolver;

// ---------------------------------------------------------------------------
// LegalPageResolver — pure precedence + fallback logic (no settings/HTTP I/O).
// Precedence: override link > internal content > empty. The requested locale is
// tried before the fallback chain; blank content (e.g. an empty "<p></p>") is
// skipped so a hollow editor value never masquerades as real content.
// ---------------------------------------------------------------------------

it('lets a non-empty override link win over content', function (): void {
    $page = (new LegalPageResolver)->resolve(
        'https://shop.example/privacy',
        ['de' => '<p>Deutscher Text</p>'],
        ['de'],
        'de',
    );

    expect($page->isExternal())->toBeTrue();
    expect($page->externalUrl)->toBe('https://shop.example/privacy');
    expect($page->html)->toBeNull();
});

it('treats a blank/whitespace override link as unset and uses content', function (): void {
    $page = (new LegalPageResolver)->resolve(
        '   ',
        ['de' => '<p>Deutscher Text</p>'],
        ['de'],
        'de',
    );

    expect($page->isExternal())->toBeFalse();
    expect($page->html)->toContain('Deutscher Text');
});

it('returns the requested locale content when present', function (): void {
    $page = (new LegalPageResolver)->resolve(
        null,
        ['de' => '<p>DE</p>', 'en' => '<p>EN</p>'],
        ['de'],
        'en',
    );

    expect($page->html)->toContain('EN');
});

it('falls back along the chain when the requested locale has no content', function (): void {
    // Only de set; an en request follows the chain [en, de] → de wins.
    $page = (new LegalPageResolver)->resolve(
        null,
        ['de' => '<p>Nur Deutsch</p>'],
        ['de'],
        'en',
    );

    expect($page->html)->toContain('Nur Deutsch');
});

it('skips blank content and falls back to the next locale with real content', function (): void {
    $page = (new LegalPageResolver)->resolve(
        null,
        ['en' => '<p></p>', 'de' => '<p>Deutsch</p>'],
        ['de'],
        'en',
    );

    expect($page->html)->toContain('Deutsch');
});

it('is empty when neither an override link nor any content exists', function (): void {
    $page = (new LegalPageResolver)->resolve(null, [], ['de'], 'en');

    expect($page->isEmpty())->toBeTrue();
    expect($page->externalUrl)->toBeNull();
    expect($page->html)->toBeNull();
});
