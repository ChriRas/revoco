<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\LegalPages;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Renders the privacy policy (/datenschutz) in the consumer's locale, resolved by
 * App\Support\LegalPages (content per locale + fallback chain + override link).
 *
 * Runs under SetConsumerLocale, so app()->getLocale() is the consumer's choice.
 * Three outcomes: 302-redirect to the operator's external override URL when set;
 * otherwise the resolved rich text, SAFE-rendered via Str::sanitizeHtml() (Filament's
 * Symfony-backed HTML sanitizer — the same tool it uses for its own TextColumn /
 * TextEntry — which strips scripts, event handlers and unsafe URL schemes even though
 * the operator is the sole trusted author); otherwise a neutral "not configured yet"
 * placeholder — never fabricated legal text.
 */
final class ShowPrivacyPolicyController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $page = LegalPages::privacy();

        if ($page->isExternal()) {
            /** @var string $url */
            $url = $page->externalUrl;

            return redirect()->away($url);
        }

        $content = $page->html !== null
            ? new HtmlString(Str::sanitizeHtml($page->html))
            : null;

        return view('legal.page', [
            'title' => __('wf.legal.privacy.title'),
            'content' => $content,
        ]);
    }
}
