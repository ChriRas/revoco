<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Operator settings of the network spam signals. `shipping_countries` are the ISO-3166
 * alpha-2 codes the merchant ships to; a submit from another resolved country adds the
 * near-neutral `country_outside_shipping` signal. Empty = signal off. Classifies only,
 * it never blocks (§ 356a).
 */
final class NetworkSignalSettings extends Settings
{
    /** @var list<string> */
    public array $shipping_countries;

    public static function group(): string
    {
        return 'network_signals';
    }
}
