<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StoreWithdrawal;
use App\Http\Requests\StoreWithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Handles the withdrawal submit (§ 356a stage 2).
 *
 * Spam signals (honeypot, soft rate-limit) are NON-BLOCKING: they only set a
 * `spam` flag + reason. Every submission is stored and the consumer is always
 * redirected to the success page — the receipt must never be prevented.
 */
final class StoreWithdrawalController extends Controller
{
    /** Submits allowed per IP per minute before the rate-limit signal trips. */
    private const int MAX_ATTEMPTS = 5;

    public function __invoke(StoreWithdrawalRequest $request, StoreWithdrawal $store): RedirectResponse
    {
        /** @var array{name: string, email: string, subject: string, orderNumber?: string|null} $data */
        $data = $request->validated();

        [$spam, $reason] = $this->evaluateSpamSignals($request);

        $store->handle(
            name: $data['name'],
            email: $data['email'],
            orderNumber: $data['orderNumber'] ?? null,
            subject: $data['subject'],
            spam: $spam,
            spamReason: $reason,
        );

        return redirect()->route('withdrawal.success');
    }

    /**
     * Returns [spam, reason] without ever blocking. Honeypot takes precedence
     * over the soft rate-limit. The IP is used only as a transient cache key —
     * it is never persisted (data minimization).
     *
     * @return array{0: bool, 1: string|null}
     */
    private function evaluateSpamSignals(Request $request): array
    {
        if (filled($request->input('website'))) {
            return [true, 'honeypot'];
        }

        $key = 'withdrawal-submit:'.sha1((string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return [true, 'throttle'];
        }

        RateLimiter::hit($key, decaySeconds: 60);

        return [false, null];
    }
}
