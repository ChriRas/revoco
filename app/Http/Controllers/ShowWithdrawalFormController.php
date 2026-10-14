<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Spam\FormRenderToken;
use Illuminate\Contracts\View\View;

/**
 * Renders the neutral § 356a BGB withdrawal form.
 *
 * Hands the view a signed render timestamp; the submit handler uses it to judge how
 * fast the form was filled (a spam signal — it never blocks the consumer).
 */
final class ShowWithdrawalFormController extends Controller
{
    public function __invoke(FormRenderToken $renderToken): View
    {
        return view('withdrawal.form', ['renderToken' => $renderToken->issue()]);
    }
}
