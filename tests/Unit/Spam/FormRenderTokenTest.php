<?php

declare(strict_types=1);

use App\Spam\FormRenderToken;
use Illuminate\Support\Facades\Crypt;

it('round-trips the render time', function () {
    $this->travelTo(now()->startOfSecond());
    $token = (new FormRenderToken)->issue();

    $this->travel(10)->seconds();

    expect((new FormRenderToken)->renderedAt($token)?->getTimestamp())->toBe(now()->subSeconds(10)->getTimestamp());
});

it('treats a missing token as no render time', function (?string $token) {
    expect((new FormRenderToken)->renderedAt($token))->toBeNull();
})->with([null, '']);

it('treats a tampered or foreign token as no render time', function () {
    $token = (new FormRenderToken)->issue();
    $tampered = substr_replace($token, $token[20] === 'A' ? 'B' : 'A', 20, 1);

    expect((new FormRenderToken)->renderedAt($tampered))->toBeNull()
        ->and((new FormRenderToken)->renderedAt('not-a-token'))->toBeNull()
        ->and((new FormRenderToken)->renderedAt(Crypt::encryptString('yesterday')))->toBeNull();
});

it('treats a token rendered in the future as no render time', function () {
    $token = Crypt::encryptString((string) now()->addMinutes(5)->getTimestamp());

    expect((new FormRenderToken)->renderedAt($token))->toBeNull();
});
