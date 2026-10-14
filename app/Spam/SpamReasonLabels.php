<?php

declare(strict_types=1);

namespace App\Spam;

use Illuminate\Support\Facades\Lang;

/**
 * The one rendering point for the stored `spam_reason` key list.
 *
 * The stored value stays machine data (snake_case keys, comma-separated); only the
 * display is translated, via `panel.spam_signal.<key>` in the current locale. A key
 * without a translation is shown raw. Sibling signal slices add only lang keys.
 */
final class SpamReasonLabels
{
    public static function render(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $labels = [];

        foreach (explode(',', $reason) as $key) {
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $translationKey = 'panel.spam_signal.'.$key;
            $labels[] = Lang::has($translationKey) ? __($translationKey) : $key;
        }

        return $labels === [] ? null : implode(', ', $labels);
    }
}
