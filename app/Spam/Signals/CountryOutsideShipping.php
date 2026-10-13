<?php

declare(strict_types=1);

namespace App\Spam\Signals;

use App\Settings\NetworkSignalSettings;
use App\Spam\SubmissionContext;

/**
 * The IP resolves to a country outside the operator's shipping countries. An empty list
 * switches the signal off; an unresolved country never fires. The settings are resolved when the
 * signal runs, not when it is built, so a settings failure cannot break the container.
 */
final class CountryOutsideShipping extends AbstractNetworkSignal
{
    public function key(): string
    {
        return 'country_outside_shipping';
    }

    protected function matches(SubmissionContext $context): bool
    {
        $country = $context->network?->country;
        $shipping = app(NetworkSignalSettings::class)->shipping_countries;

        return $country !== null && $shipping !== [] && ! in_array($country, $shipping, true);
    }
}
