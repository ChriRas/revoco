<?php

declare(strict_types=1);

namespace App\Support;

use App\Settings\WithdrawalScopeSettings;
use Illuminate\Support\Arr;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Turns the operator-declared WithdrawalScopeSettings into the consumer form's
 * explanatory copy: an intro sentence that names the enabled contract categories
 * and a matching `subject` field label, both rendered in the request locale.
 *
 * DISPLAY ONLY (§ 356a): this class only produces wording. It never touches the
 * submit path, adds no field, and changes no validation — a consumer whose case
 * matches none of the enabled categories still uses the same free-text `subject`.
 * When no category is enabled (or the settings row is unseeded) it falls back to
 * today's generic copy, so the form never 500s on a misconfigured settings store.
 */
final class WithdrawalScope
{
    /**
     * The operator-enabled categories in canonical order (goods → services →
     * digital). Empty when none are enabled or the settings row is missing
     * (§ 356a fallback → generic copy).
     *
     * @return list<string>
     */
    public static function enabledCategories(): array
    {
        try {
            $settings = app(WithdrawalScopeSettings::class);
        } catch (MissingSettings) {
            return [];
        }

        $enabled = [];

        if ($settings->offers_goods) {
            $enabled[] = 'goods';
        }

        if ($settings->offers_services) {
            $enabled[] = 'services';
        }

        if ($settings->offers_digital) {
            $enabled[] = 'digital';
        }

        return $enabled;
    }

    /**
     * Intro sentence for the form header: names the enabled categories, or the
     * generic fallback when none are enabled (behaviour-preserving default).
     */
    public static function intro(): string
    {
        $categories = self::enabledCategories();

        return $categories === []
            ? (string) __('wf.scope.intro_generic')
            : (string) __('wf.scope.intro', ['categories' => self::categoryList()]);
    }

    /**
     * The `subject` field label. Names the enabled categories when declared, else
     * the generic three-way label. DISPLAY ONLY — the field name, validation, and
     * required state are untouched.
     */
    public static function subjectLabel(): string
    {
        $categories = self::enabledCategories();

        return $categories === []
            ? (string) __('wf.field.subject.label')
            : (string) __('wf.scope.subject_label', ['categories' => self::categoryList()]);
    }

    /**
     * The enabled category labels joined into a localized, grammatical list
     * (e.g. "Waren, Dienstleistungen und digitale Inhalte"). Comma-separates all
     * but the last, which is joined with the locale's conjunction ("und" / "and")
     * — German uses no Oxford comma. A single label returns itself.
     */
    private static function categoryList(): string
    {
        $labels = array_map(
            static fn (string $category): string => (string) __('wf.scope.'.$category),
            self::enabledCategories(),
        );

        $conjunction = (string) __('wf.scope.conjunction');

        return Arr::join($labels, ', ', ' '.$conjunction.' ');
    }
}
