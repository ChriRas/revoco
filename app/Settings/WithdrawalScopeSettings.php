<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Operator-declared contract/goods types the merchant actually offers — goods,
 * services, digital content — grounded in the § 312g / § 355 f. BGB withdrawal
 * categories (not invented). Managed on the Filament "Withdrawal scope" page and
 * read by App\Support\WithdrawalScope to tailor the consumer form's explanatory
 * copy.
 *
 * DISPLAY ONLY (§ 356a): these toggles shape wording/labels — they never gate the
 * submit and never remove the free-text `subject` fallback. Defaults are all false
 * (→ generic copy), so an upgrade is behaviour-preserving until the operator opts
 * into naming specific categories.
 */
final class WithdrawalScopeSettings extends Settings
{
    public bool $offers_goods;

    public bool $offers_services;

    public bool $offers_digital;

    public static function group(): string
    {
        return 'withdrawal_scope';
    }
}
