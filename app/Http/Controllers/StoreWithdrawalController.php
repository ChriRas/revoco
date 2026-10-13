<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StoreWithdrawal;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Network\NetworkLookup;
use App\Spam\SpamScorer;
use App\Spam\SubmissionContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

/**
 * Handles the withdrawal submit (§ 356a stage 2).
 *
 * The spam score engine (App\Spam\SpamScorer) is NON-BLOCKING: it only yields a
 * score, a `spam_level` and the signals that fired. Every submission is stored and
 * the consumer is always redirected to the success page — the receipt must never be
 * prevented.
 */
final class StoreWithdrawalController extends Controller
{
    public function __invoke(StoreWithdrawalRequest $request, StoreWithdrawal $store, SpamScorer $scorer, NetworkLookup $lookup): RedirectResponse
    {
        /** @var array{name: string, email: string, subject: string, orderNumber?: string|null} $data */
        $data = $request->validated();

        // Transient: the IP and the user agent are read in memory and never persisted; only
        // the country and ASN derived locally from the IP are stored. The lookup never throws.
        $network = $lookup->lookup($request->ip());

        $assessment = $scorer->assess(new SubmissionContext(
            name: $data['name'],
            orderNumber: $data['orderNumber'] ?? null,
            subject: $data['subject'],
            honeypotFilled: filled($request->input('website')),
            renderToken: is_string($token = $request->input('rendered')) ? $token : null,
            userAgent: $request->userAgent(),
            ip: $request->ip(),
            submittedAt: CarbonImmutable::now(),
            network: $network,
        ));

        $store->handle(
            name: $data['name'],
            email: $data['email'],
            orderNumber: $data['orderNumber'] ?? null,
            subject: $data['subject'],
            assessment: $assessment,
            network: $network,
        );

        return redirect()->route('withdrawal.success');
    }
}
