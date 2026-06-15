<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * On-screen confirmation that the withdrawal was received (§ 356a stage 2).
 *
 * This is the on-screen confirmation only — the durable-medium acknowledgment
 * (e-mail) is Phase 4. No PII echo, no reference number on this page.
 */
final class ShowWithdrawalSuccessController extends Controller
{
    public function __invoke(): View
    {
        return view('withdrawal.success');
    }
}
