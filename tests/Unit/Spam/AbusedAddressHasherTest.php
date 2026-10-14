<?php

declare(strict_types=1);

use App\Support\AbusedAddressHasher;

it('normalises by trimming and lowercasing only', function () {
    expect(AbusedAddressHasher::normalize("  Kunde+Tag@Example.COM \n"))->toBe('kunde+tag@example.com')
        ->and(AbusedAddressHasher::normalize('k.unde@example.com'))->toBe('k.unde@example.com');
});

it('gives the same 64-hex hash for addresses that differ only in case and surrounding whitespace', function () {
    $hasher = new AbusedAddressHasher;

    expect($hasher->hash(' Kunde@Example.com '))->toMatch('/^[0-9a-f]{64}$/')
        ->toBe($hasher->hash('kunde@example.com'))
        ->not->toBe($hasher->hash('kunde+tag@example.com'));
});

it('does not use the plain app key and changes with APP_KEY', function () {
    $hasher = new AbusedAddressHasher;
    $first = $hasher->hash('kunde@example.com');

    expect($first)->not->toBe(hash_hmac('sha256', 'kunde@example.com', (string) config('app.key')));

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    expect($hasher->hash('kunde@example.com'))->not->toBe($first);
});

it('refuses to hash without an app key', function () {
    config(['app.key' => '']);

    (new AbusedAddressHasher)->hash('kunde@example.com');
})->throws(RuntimeException::class);

it('derives the documented hash from a fixed key (pins the derivation)', function () {
    config(['app.key' => 'base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=']);

    expect((new AbusedAddressHasher)->hash('kunde@example.com'))
        ->toBe('2ff3f810a25dd637da804134341cb856ee6bc95f6d66e2571c197aadaa496bb2');
});

it('refuses a malformed base64 app key instead of hashing under an empty key', function () {
    config(['app.key' => 'base64:%%%not-base64%%%']);

    (new AbusedAddressHasher)->hash('kunde@example.com');
})->throws(RuntimeException::class);
