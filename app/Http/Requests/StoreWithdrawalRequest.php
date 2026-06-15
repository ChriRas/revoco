<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the three legally mandatory § 356a Abs. 2 fields.
 *
 * Validating these is LAWFUL collection of the mandated information — distinct
 * from unlawful obstruction (captcha / contract-existence gate / spam rejection),
 * which this app never does. The honeypot field is not validated here; spam
 * signals are evaluated in the controller and never block the submit.
 */
final class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // Form field is camelCase `orderNumber` (slice-002); persisted as `order_number`.
            'orderNumber' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Custom messages reuse the form's German copy (consistent formal "Sie").
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('wf.field.name.error'),
            'email.required' => __('wf.field.email.error'),
            'email.email' => __('wf.field.email.invalid'),
            'subject.required' => __('wf.field.subject.error'),
        ];
    }
}
