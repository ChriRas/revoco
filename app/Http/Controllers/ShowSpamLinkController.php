<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SpamLinkAction;
use App\Models\Withdrawal;
use App\Spam\SpamLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Confirmation page behind a signed mail link. Read-only: mail scanners prefetch
 * links, so a GET never changes state; the page's button POSTs to the same URL.
 * PII-free by design — entry number, receipt time and status only.
 */
final class ShowSpamLinkController extends Controller
{
    public function __invoke(Request $request, int $withdrawal, SpamLinkAction $action): Response
    {
        $row = Withdrawal::find($withdrawal);

        if ($row === null) {
            return response()->view('spam-link.gone', status: 404);
        }

        return response()->view('spam-link.confirm', [
            'withdrawal' => $row,
            'action' => $action,
            'open' => $request->query('state') === SpamLinks::state($row),
            'formAction' => SpamLinks::origin().$request->getRequestUri(),
        ]);
    }
}
