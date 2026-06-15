<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Renders the neutral § 356a BGB withdrawal form (render-only).
 *
 * The form posts to the `withdrawal.store` stub; the real submit handler
 * (validation, persistence, async mail/push) lands in slice-003.
 */
final class ShowWithdrawalFormController extends Controller
{
    public function __invoke(): View
    {
        return view('withdrawal.form');
    }
}
