<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Settings\LegalSettings;
use App\Support\LegalPages;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * Renders the imprint (/impressum) in the consumer's locale, resolved by
 * App\Support\LegalPages (override link > structured fields > empty placeholder).
 *
 * Runs under SetConsumerLocale, so app()->getLocale() is the consumer's choice.
 * Three outcomes:
 *   1. 302-redirect to the operator's external override URL (imprint_link set).
 *   2. Structured § 5 DDG fields (locale-independent operator data) + the per-locale
 *      addendum resolved via the fallback chain; optional empty fields are omitted.
 *   3. Neutral "not configured yet" placeholder — never fabricated legal text.
 *
 * The addendum is safe-rendered via Str::sanitizeHtml() (Filament's Symfony-backed
 * sanitizer) even though the operator is the sole trusted author.
 */
final class ShowImprintController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $page = LegalPages::imprint();

        if ($page->isExternal()) {
            /** @var string $url */
            $url = $page->externalUrl;

            return redirect()->away($url);
        }

        // Read the structured imprint fields. LegalPages::imprint() already handles
        // the MissingSettings case for the override check; a second try/catch is
        // needed here because the structured fields are read independently.
        try {
            $settings = app(LegalSettings::class);
            $fields = $this->buildFields($settings);
        } catch (MissingSettings) {
            $fields = [];
        }

        $addendum = $page->html !== null
            ? new HtmlString(Str::sanitizeHtml($page->html))
            : null;

        $isEmpty = $fields === [] && $addendum === null;

        return view('legal.imprint', [
            'title' => __('wf.legal.imprint.title'),
            'fields' => $fields,
            'addendum' => $addendum,
            'isEmpty' => $isEmpty,
        ]);
    }

    /**
     * Maps the LegalSettings imprint properties to a structured array for the view.
     * Only non-blank values are included so the template can iterate without
     * conditional checks per field (the "omit empty optional fields" rule).
     *
     * Returns a list of groups, each with a heading key and a list of
     * [label_key, value] rows.
     *
     * @return list<array{heading: string, rows: list<array{label: string, value: string}>}>
     */
    private function buildFields(LegalSettings $settings): array
    {
        $groups = [];

        // Entity group — § 5 Abs. 1 Nr. 1 DDG.
        $entityRows = $this->filterRows([
            ['label' => 'wf.legal.imprint.field.name',           'value' => $settings->imprint_name],
            ['label' => 'wf.legal.imprint.field.legal_form',     'value' => $settings->imprint_legal_form],
            ['label' => 'wf.legal.imprint.field.represented_by', 'value' => $settings->imprint_represented_by],
            ['label' => 'wf.legal.imprint.field.address',        'value' => $settings->imprint_address],
        ]);
        if ($entityRows !== []) {
            $groups[] = ['heading' => 'wf.legal.imprint.heading.entity', 'rows' => $entityRows];
        }

        // Contact group — § 5 Abs. 1 Nr. 2 DDG.
        $contactRows = $this->filterRows([
            ['label' => 'wf.legal.imprint.field.email',        'value' => $settings->imprint_email],
            ['label' => 'wf.legal.imprint.field.phone',        'value' => $settings->imprint_phone],
            ['label' => 'wf.legal.imprint.field.contact_note', 'value' => $settings->imprint_contact_note],
        ]);
        if ($contactRows !== []) {
            $groups[] = ['heading' => 'wf.legal.imprint.heading.contact', 'rows' => $contactRows];
        }

        // Register group — § 5 Abs. 1 Nr. 4 DDG.
        $registerRows = $this->filterRows([
            ['label' => 'wf.legal.imprint.field.register_court',  'value' => $settings->imprint_register_court],
            ['label' => 'wf.legal.imprint.field.register_number', 'value' => $settings->imprint_register_number],
        ]);
        if ($registerRows !== []) {
            $groups[] = ['heading' => 'wf.legal.imprint.heading.register', 'rows' => $registerRows];
        }

        // Tax group — § 5 Abs. 1 Nr. 6 DDG.
        $taxRows = $this->filterRows([
            ['label' => 'wf.legal.imprint.field.vat_id',      'value' => $settings->imprint_vat_id],
            ['label' => 'wf.legal.imprint.field.business_id', 'value' => $settings->imprint_business_id],
        ]);
        if ($taxRows !== []) {
            $groups[] = ['heading' => 'wf.legal.imprint.heading.tax', 'rows' => $taxRows];
        }

        // Professional group — § 5 Abs. 1 Nr. 3 and 5 DDG (optional regulated professions).
        $professionalRows = $this->filterRows([
            ['label' => 'wf.legal.imprint.field.supervisory_authority', 'value' => $settings->imprint_supervisory_authority],
            ['label' => 'wf.legal.imprint.field.chamber',               'value' => $settings->imprint_chamber],
            ['label' => 'wf.legal.imprint.field.job_title',             'value' => $settings->imprint_job_title],
            ['label' => 'wf.legal.imprint.field.professional_rules',    'value' => $settings->imprint_professional_rules],
            ['label' => 'wf.legal.imprint.field.liquidation_note',      'value' => $settings->imprint_liquidation_note],
        ]);
        if ($professionalRows !== []) {
            $groups[] = ['heading' => 'wf.legal.imprint.heading.professional', 'rows' => $professionalRows];
        }

        return $groups;
    }

    /**
     * Keeps only rows where the value is non-blank. Translates label keys on output.
     *
     * @param  list<array{label: string, value: string|null}>  $rows
     * @return list<array{label: string, value: string}>
     */
    private function filterRows(array $rows): array
    {
        $filtered = [];

        foreach ($rows as $row) {
            $value = trim($row['value'] ?? '');

            if ($value !== '') {
                $filtered[] = [
                    'label' => __($row['label']),
                    'value' => $value,
                ];
            }
        }

        return $filtered;
    }
}
