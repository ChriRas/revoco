<?php

declare(strict_types=1);

namespace App\Support;

use Collator;
use Locale;
use ResourceBundle;

/**
 * ISO-3166 alpha-2 country codes with their names in a locale, from the ICU data of ext-intl.
 * ICU also carries a few region-like codes that no IP lookup returns (EU, UN, the exceptional
 * reservations); they are left out.
 */
final class CountryNames
{
    /** @var list<string> */
    private const array NOT_COUNTRIES = ['AC', 'CP', 'CQ', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'ZZ'];

    /**
     * @return array<string, string> code => name, sorted by name in the locale
     */
    public static function options(string $locale): array
    {
        $bundle = ResourceBundle::create('en', 'ICUDATA-region');
        $names = [];

        if ($bundle !== null && ($countries = $bundle['Countries']) instanceof ResourceBundle) {
            foreach ($countries as $code => $unused) {
                if (is_string($code) && preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, self::NOT_COUNTRIES, true)) {
                    $names[$code] = self::name($code, $locale);
                }
            }
        }

        $collator = new Collator($locale);
        uasort($names, fn (string $a, string $b): int => (int) $collator->compare($a, $b));

        return $names;
    }

    public static function name(string $code, string $locale): string
    {
        return (string) Locale::getDisplayRegion('-'.$code, $locale);
    }
}
