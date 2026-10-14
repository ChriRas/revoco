<?php

declare(strict_types=1);

use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Filament\Resources\WithdrawalResource\WithdrawalPreview;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

it('takes the newest row of the inbox and of the spam folder as the tab default', function () {
    $olderInbox = Withdrawal::factory()->create(['created_at' => now()->subHours(3)]);
    $newestInbox = Withdrawal::factory()->yellow()->create(['created_at' => now()->subHour()]);
    $newestSpam = Withdrawal::factory()->spam()->create(['created_at' => now()->subHours(2)]);
    Withdrawal::factory()->confirmedSpam()->create(['created_at' => now()->subHours(5)]);

    expect(WithdrawalPreview::newestIn('inbox')?->is($newestInbox))->toBeTrue()
        ->and(WithdrawalPreview::newestIn(null)?->is($newestInbox))->toBeTrue()
        ->and(WithdrawalPreview::newestIn('spam')?->is($newestSpam))->toBeTrue()
        ->and($olderInbox->is(WithdrawalPreview::newestIn('inbox')))->toBeFalse();
});

it('has no default for an empty tab', function () {
    Withdrawal::factory()->create();

    expect(WithdrawalPreview::newestIn('spam'))->toBeNull();
});

it('prepares the display values of a withdrawal', function () {
    $row = Withdrawal::factory()->autoSorted()->create([
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'order_number' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'created_at' => Carbon::parse('2026-10-14 11:30:00', 'Europe/Berlin'),
    ]);

    expect(WithdrawalPreview::data($row))->toBe([
        'id' => $row->id,
        'received' => '14.10.2026 11:30',
        'name' => 'Max Mustermann',
        'email' => 'kunde@example.com',
        'order' => 'A-12345',
        'subject' => 'Schlauchboot Modell X',
        'level' => __('panel.spam_level.red'),
        'levelColor' => 'danger',
        'reasons' => __('panel.spam_signal.honeypot').', '.__('panel.spam_signal.user_agent_ancient'),
        'decision' => __('panel.spam_verdict.spam_auto'),
    ]);
});

it('keys the display values of several rows by record id', function () {
    $rows = Withdrawal::factory()->count(2)->create();

    expect(array_keys(WithdrawalPreview::dataFor($rows)))->toBe($rows->pluck('id')->all());
});

/** The rendered preview block of the list page (the panel up to its style tag). */
function previewBlock(Testable $component): string
{
    $html = $component->html();
    $start = strpos($html, 'data-testid="withdrawal-preview"');
    expect($start)->not->toBeFalse();

    return substr($html, (int) $start, (int) strpos($html, '<style>', (int) $start) - (int) $start);
}

it('shows the newest row of the active tab in the preview panel', function () {
    $this->actingAs(User::factory()->create());
    Withdrawal::factory()->create(['name' => 'Erika Mustermann', 'created_at' => now()->subHours(3)]);
    Withdrawal::factory()->create(['name' => 'Max Mustermann', 'email' => 'kunde@example.com', 'subject' => 'Schlauchboot Modell X', 'created_at' => now()->subHour()]);
    Withdrawal::factory()->autoSorted()->create(['name' => 'Spam Bot', 'created_at' => now()->subHours(2)]);

    $component = livewire(ListWithdrawals::class);
    $inbox = previewBlock($component);

    expect($inbox)->toContain(__('panel.preview.heading'))
        ->toContain('>Max Mustermann<')
        ->toContain('>kunde@example.com<')
        ->toContain('>Schlauchboot Modell X<')
        ->not->toContain('Spam Bot');

    $spam = previewBlock($component->set('activeTab', 'spam'));

    expect($spam)->toContain('>Spam Bot<')
        ->toContain(__('panel.spam_verdict.spam_auto'))
        ->not->toContain('Max Mustermann');
});

it('keeps the tab default when a search hides the newest row', function () {
    $this->actingAs(User::factory()->create());
    Withdrawal::factory()->create(['name' => 'Erika Mustermann', 'created_at' => now()->subHours(3)]);
    Withdrawal::factory()->create(['name' => 'Max Mustermann', 'created_at' => now()->subHour()]);

    $preview = previewBlock(livewire(ListWithdrawals::class)->searchTable('Erika'));

    expect($preview)->toContain('>Max Mustermann<');
});

it('embeds the data of the current page rows only', function () {
    $this->actingAs(User::factory()->create());
    $inbox = Withdrawal::factory()->create(['email' => 'kunde@example.com']);
    Withdrawal::factory()->spam()->create(['email' => 'opfer1@example.com']);

    $preview = previewBlock(livewire(ListWithdrawals::class));

    expect($preview)->toContain('kunde@example.com')
        ->toContain('\\u0022'.$inbox->id.'\\u0022:')
        ->not->toContain('opfer1@example.com');
});

it('embeds the rows of the shown page only and follows the page change', function () {
    $this->actingAs(User::factory()->create());
    foreach (range(1, 12) as $i) {
        Withdrawal::factory()->create(['email' => "kunde{$i}@example.com", 'created_at' => now()->subMinutes(100 - $i)]);
    }

    $component = livewire(ListWithdrawals::class);

    // Newest first, 10 per page: kunde1 and kunde2 are on page 2.
    expect(previewBlock($component))->toContain('kunde3@example.com')->not->toContain('kunde1@example.com');
    expect(previewBlock($component->call('gotoPage', 2)))->toContain('kunde1@example.com')->not->toContain('kunde3@example.com');
});

it('renders hostile content inert', function () {
    $this->actingAs(User::factory()->create());
    Withdrawal::factory()->create(['name' => '<script>alert(1)</script>', 'subject' => '"><img src=x onerror=alert(2)>']);

    $preview = previewBlock(livewire(ListWithdrawals::class));

    expect($preview)->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('shows a note instead of the preview on an empty tab', function () {
    $this->actingAs(User::factory()->create());
    Withdrawal::factory()->create();

    $preview = previewBlock(livewire(ListWithdrawals::class)->set('activeTab', 'spam'));

    // Same size as a filled panel: the grid stays in place, hidden, under the note.
    expect($preview)->toContain(__('panel.preview.empty'))
        ->toContain('wf-preview-grid')
        ->toContain('style="visibility: hidden"');
});

it('puts the preview above the tabs and the table', function () {
    $this->actingAs(User::factory()->create());
    Withdrawal::factory()->create();

    $html = livewire(ListWithdrawals::class)->html();

    expect(strpos($html, 'data-testid="withdrawal-preview"'))->toBeLessThan((int) strpos($html, 'fi-tabs'))
        ->and(strpos($html, 'fi-tabs'))->toBeLessThan((int) strpos($html, 'fi-ta-ctn'));
});

it('names a not-spam correction below the level badge in the list', function () {
    $this->actingAs(User::factory()->create());
    Withdrawal::factory()->spam()->notSpam()->create(['name' => 'Max Mustermann']);
    Withdrawal::factory()->notSpam()->create(['name' => 'Erika Mustermann']);

    $html = livewire(ListWithdrawals::class)->html();

    // Only the red row whose level the decision overrides carries the note — once.
    expect(substr_count($html, __('panel.spam_verdict.not_spam_operator')))->toBe(1);
});
