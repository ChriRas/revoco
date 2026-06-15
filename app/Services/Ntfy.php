<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Minimal ntfy publish client (https://docs.ntfy.sh/publish/).
 *
 * Publishes via POST to `<server>/<topic>` with the message as the body and the
 * Title / Tags headers; authenticates with a Bearer access token when configured.
 * Callers must keep the payload PII-free — this client does not inspect content.
 */
final class Ntfy
{
    /**
     * @param  list<string>  $tags
     */
    public function publish(string $message, string $title, array $tags = []): void
    {
        $server = rtrim(Config::string('revoco.ntfy.server', 'https://ntfy.sh'), '/');
        $topic = Config::string('revoco.ntfy.topic', '');

        $request = Http::withHeaders(array_filter([
            'Title' => $title,
            'Tags' => $tags === [] ? null : implode(',', $tags),
        ]));

        $token = config('revoco.ntfy.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        $request->withBody($message, 'text/plain')
            ->post("{$server}/{$topic}")
            ->throw();
    }
}
