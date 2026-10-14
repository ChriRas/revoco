<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsNotSpam;
use App\Enums\SpamLinkAction;
use App\Enums\SpamVerdict;
use App\Mail\WithdrawalNotification;
use App\Models\Withdrawal;
use App\Spam\SpamLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Extracts the signed "mark as spam" URL from the rendered merchant notification. */
function markSpamUrlFromMail(Withdrawal $row): string
{
    $html = (new WithdrawalNotification($row))->locale('de')->render();

    preg_match('~href="([^"]*/spam/\d+/spam\?[^"]*)"~', $html, $matches);
    expect($matches)->not->toBeEmpty();

    return html_entity_decode($matches[1]);
}

/** Path plus query of an absolute link, as the app sees it behind a proxy. */
function pathAndQuery(string $url): string
{
    $parts = parse_url($url);

    return $parts['path'].'?'.$parts['query'];
}

it('shows a PII-free confirmation page on GET and changes nothing', function () {
    $row = Withdrawal::factory()->create(['name' => 'Max Mustermann', 'email' => 'kunde@example.com', 'subject' => 'Schlauchboot']);

    $response = $this->get(markSpamUrlFromMail($row))
        ->assertOk()
        ->assertSee('Vorgang Nr. '.$row->id)
        ->assertSee('Als Spam markieren')
        ->assertDontSee('Max Mustermann')
        ->assertDontSee('kunde@example.com')
        ->assertDontSee('Schlauchboot')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->getContent())->toContain('name="robots" content="noindex, nofollow"');
    expect($row->fresh()?->spam_verdict)->toBeNull();
});

it('confirms the row as spam on POST, redirects to the page and ignores a repeat', function () {
    $this->travelTo(now()->startOfSecond());
    $row = Withdrawal::factory()->create();
    $url = markSpamUrlFromMail($row);

    $this->post($url)->assertRedirect($url);

    $confirmedAt = $row->fresh()?->spam_verdict_at;
    expect($row->fresh()?->spam_verdict)->toBe(SpamVerdict::Spam)
        ->and($confirmedAt)->not->toBeNull();

    // The page after the redirect confirms the change; a later reload shows the link as used.
    $this->get($url)
        ->assertOk()
        ->assertSee('Bestätigter Spam')
        ->assertSee('Erledigt: als Spam markiert')
        ->assertDontSee('bereits verwendet')
        ->assertDontSee('<form', false);

    $this->get($url)
        ->assertOk()
        ->assertSee('bereits verwendet')
        ->assertDontSee('Erledigt');

    $this->travel(1)->hour();
    $this->post($url)->assertRedirect($url);
    expect($row->fresh()?->spam_verdict_at?->equalTo($confirmedAt))->toBeTrue();
});

it('shows a link as no longer valid once the status changed elsewhere', function () {
    $row = Withdrawal::factory()->yellow()->create();
    $url = markSpamUrlFromMail($row);

    app(MarkWithdrawalAsNotSpam::class)->handle($row);

    $this->get($url)->assertOk()->assertSee('bereits verwendet')->assertDontSee('<form', false);
    $this->post($url);

    expect($row->fresh()?->spam_verdict)->toBe(SpamVerdict::NotSpam);
});

it('confirms a not-spam correction on the page after the redirect', function () {
    $row = Withdrawal::factory()->spam()->create();
    $url = SpamLinks::url($row, SpamLinkAction::NotSpam);

    $this->followingRedirects()
        ->post($url)
        ->assertOk()
        ->assertSee('Erledigt: als „Kein Spam“ markiert')
        ->assertDontSee('bereits verwendet');

    expect($row->fresh()?->spam_verdict)->toBe(SpamVerdict::NotSpam);
});

it('shows no success for a repeated POST that changed nothing', function () {
    $row = Withdrawal::factory()->create();
    $url = markSpamUrlFromMail($row);

    $this->post($url);
    $this->get($url);

    $this->followingRedirects()
        ->post($url)
        ->assertOk()
        ->assertSee('bereits verwendet')
        ->assertDontSee('Erledigt');
});

it('names an auto-sorted row as automatic spam on the link page', function () {
    $row = Withdrawal::factory()->autoSorted()->create();

    $this->get(SpamLinks::url($row, SpamLinkAction::NotSpam))
        ->assertOk()
        ->assertSee('Spam (automatisch)')
        ->assertDontSee('Bestätigter Spam');
});

it('rejects a tampered signature with a neutral 403 page', function () {
    $row = Withdrawal::factory()->create();
    $url = markSpamUrlFromMail($row);

    $this->get(str_replace('/spam/'.$row->id.'/spam', '/spam/'.$row->id.'/not-spam', $url))
        ->assertForbidden()
        ->assertSee('Link ungültig')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->post(preg_replace('/signature=[^&]+/', 'signature=deadbeef', $url) ?? '')->assertForbidden();
    expect($row->fresh()?->spam_verdict)->toBeNull();
});

it('rejects an expired link with 403', function () {
    $row = Withdrawal::factory()->create();
    $url = markSpamUrlFromMail($row);

    $this->travel(SpamLinks::TTL_DAYS + 1)->days();

    $this->get($url)->assertForbidden()->assertSee('Link ungültig');
    $this->post($url)->assertForbidden();
    expect($row->fresh()?->spam_verdict)->toBeNull();
});

it('answers 404 with a neutral page for a purged row', function () {
    $row = Withdrawal::factory()->create(['name' => 'Max Mustermann']);
    $url = markSpamUrlFromMail($row);
    $row->delete();

    $this->get($url)->assertNotFound()->assertSee('existiert nicht mehr')->assertDontSee('Max Mustermann');
    $this->post($url)->assertNotFound();
});

it('renders the settings page test mail sample without a link', function () {
    $sample = new Withdrawal([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'subject' => 'Test',
        'locale' => 'de',
    ]);
    $sample->created_at = now();

    $html = (new WithdrawalNotification($sample))->locale('de')->render();

    expect($html)->not->toContain('/spam/');
});

it('keeps a link issued under an https APP_URL valid behind a TLS-terminating proxy', function () {
    config(['app.url' => 'https://widerruf.example']);
    $row = Withdrawal::factory()->create();
    $url = markSpamUrlFromMail($row);

    expect($url)->toStartWith('https://widerruf.example/spam/'.$row->id.'/spam?');

    // The app sees plain http on another host.
    $seen = 'http://localhost'.pathAndQuery($url);

    $page = $this->get($seen)->assertOk();
    expect($page->getContent())->toContain('action="https://widerruf.example/spam/'.$row->id.'/spam?');

    $this->post($seen)->assertRedirect($url);
    expect($row->fresh()?->spam_verdict)->toBe(SpamVerdict::Spam);
});

it('applies the not-spam link of a digest entry', function () {
    $row = Withdrawal::factory()->spam()->create();

    $this->post(SpamLinks::url($row, SpamLinkAction::NotSpam))->assertRedirect();

    expect($row->fresh()?->spam_verdict)->toBe(SpamVerdict::NotSpam);
});
