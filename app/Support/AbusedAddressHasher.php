<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pseudonymises an e-mail address for the abused-address memory: trim + lowercase, then
 * HMAC-SHA256 under a subkey derived from `APP_KEY` (domain-separated, so the hash key is
 * not the application key itself). Rotating `APP_KEY` changes every hash and thereby resets
 * the memory; that is accepted for a soft signal that expires anyway.
 */
final class AbusedAddressHasher
{
    /** Domain-separation label of the subkey. */
    private const string KEY_LABEL = 'revoco.abused-address';

    public static function normalize(#[\SensitiveParameter] string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function hash(#[\SensitiveParameter] string $email): string
    {
        return hash_hmac('sha256', self::normalize($email), $this->subkey());
    }

    private function subkey(): string
    {
        $key = Config::string('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is not set; the abused-address hash needs it.');
        }

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'), true);

            if ($key === false || $key === '') {
                throw new RuntimeException('APP_KEY is not valid base64; the abused-address hash needs it.');
            }
        }

        return hash_hmac('sha256', self::KEY_LABEL, $key, true);
    }
}
