<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SpamLinkAction;
use App\Models\Withdrawal;
use App\Spam\SpamLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Applies the operator's verdict from a signed mail link. Only valid while the row's
 * verdict still equals the one at issue (`state`); a repeated or outdated request changes
 * nothing. Redirects to the same URL (PRG), on the public origin and not the one the
 * app sees behind its proxy.
 */
final class ApplySpamLinkController extends Controller
{
    public function __invoke(Request $request, int $withdrawal, SpamLinkAction $action): RedirectResponse|Response
    {
        $row = Withdrawal::find($withdrawal);

        if ($row === null) {
            return response()->view('spam-link.gone', status: 404);
        }

        if ($request->query('state') === SpamLinks::state($row)) {
            $action->applyTo($row);
        }

        return redirect()->to(SpamLinks::origin().$request->getRequestUri());
    }
}
