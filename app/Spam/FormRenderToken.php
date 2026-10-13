<?php

declare(strict_types=1);

namespace App\Spam;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Signed render timestamp of the withdrawal form (the "rendered" hidden field).
 *
 * The token is the render Unix time encrypted with the APP_KEY: authenticated, and
 * the bot cannot read or forge the time. A missing, tampered or future token is not
 * an error for the consumer — it only makes `renderedAt()` return null, which the
 * `render_token_invalid` signal turns into points.
 */
final class FormRenderToken
{
    public function issue(): string
    {
        return Crypt::encryptString((string) CarbonImmutable::now()->getTimestamp());
    }

    /** The render time, or null when the token is missing, tampered with or from the future. */
    public function renderedAt(?string $token): ?CarbonImmutable
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $timestamp = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        if (! ctype_digit($timestamp)) {
            return null;
        }

        $renderedAt = CarbonImmutable::createFromTimestamp((int) $timestamp);

        return $renderedAt->isFuture() ? null : $renderedAt;
    }
}
