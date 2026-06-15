<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

/**
 * Placeholder for the withdrawal submit endpoint.
 *
 * Exists so the form's `action` + `@csrf` resolve against a named route now;
 * slice-003 replaces this with FormRequest validation, persistence, and the
 * async acknowledgment mail + operator push.
 */
final class StoreWithdrawalController extends Controller
{
    public function __invoke(): never
    {
        abort(Response::HTTP_NOT_IMPLEMENTED);
    }
}
