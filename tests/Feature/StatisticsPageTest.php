<?php

declare(strict_types=1);

use App\Actions\MarkWithdrawalAsSpam;
use App\Filament\Pages\Statistics;
use App\Filament\Resources\WithdrawalResource;
use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Filament\Widgets\SignalBreakdownTable;
use App\Filament\Widgets\StatisticsHistoryChart;
use App\Filament\Widgets\StatisticsOverview;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalDailyCount;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(Carbon\Carbon::parse('2026-10-15 12:00', 'Europe/Berlin'));
    app()->setLocale('en');
    config(['operator.locale' => 'en']);
});

/** Seeds one counter row the way slice-033's recorder stores it. */
function seedStatsCount(string $day, string $level, string $outcome, string $signal, int $count): void
{
    WithdrawalDailyCount::query()->create(['day' => $day, 'level' => $level, 'outcome' => $outcome, 'signal' => $signal, 'count' => $count]);
}

/** Counters for this month (4 genuine, 2 suspected, 7 spam), a March day and a 2025 day. */
function seedStatsFixture(): void
{
    seedStatsCount('2026-10-15', 'green', 'none', '', 3);
    seedStatsCount('2026-10-15', 'yellow', 'none', '', 2);
    seedStatsCount('2026-10-15', 'red', 'none', '', 1);
    seedStatsCount('2026-10-15', 'red', 'none', 'honeypot', 1);
    seedStatsCount('2026-10-15', 'red', 'spam_auto', '', 4);
    seedStatsCount('2026-10-15', 'red', 'spam_auto', 'honeypot', 4);
    seedStatsCount('2026-10-15', 'red', 'spam_auto', 'throttle', 2);
    seedStatsCount('2026-10-03', 'red', 'not_spam', '', 1);
    seedStatsCount('2026-10-03', 'red', 'not_spam', 'honeypot', 1);
    seedStatsCount('2026-10-03', 'green', 'spam_operator', '', 1);
    seedStatsCount('2026-10-03', 'green', 'spam_operator', 'name_without_space', 1);
    seedStatsCount('2026-10-03', 'yellow', 'spam_operator', '', 1);
    seedStatsCount('2026-03-10', 'green', 'none', '', 137);
    seedStatsCount('2025-05-20', 'green', 'none', '', 4242);
}

/**
 * The tiles of the overview widget as label => value.
 *
 * @return array<string, mixed>
 */
function tilesOf(string $period): array
{
    $widget = livewire(StatisticsOverview::class, ['pageFilters' => ['period' => $period]])->instance();

    return collect((fn (): array => $this->getStats())->call($widget))
        ->mapWithKeys(fn ($stat): array => [(string) $stat->getLabel() => (int) preg_replace('/\D/', '', (string) $stat->getValue())])
        ->all();
}

describe('access and navigation', function () {
    it('redirects a guest to the login', function () {
        $this->get('/admin/statistics')->assertRedirect('/admin/login');
    });

    it('opens for a logged-in operator', function () {
        $this->actingAs(User::factory()->create())
            ->get('/admin/statistics')
            ->assertOk()
            ->assertSee('Statistics');
    });

    it('lists Statistics right after Withdrawals in every locale', function (string $locale, array $labels) {
        $this->actingAs(User::factory()->create());
        app()->setLocale($locale);

        livewire(Statistics::class)->assertOk();

        $items = collect(Filament::getNavigation())->flatMap(fn ($group) => $group->getItems())->map(fn ($item) => $item->getLabel());

        expect($items->take(2)->values()->all())->toBe($labels);
    })->with([
        'en' => ['en', ['Withdrawals', 'Statistics']],
        'de' => ['de', ['Widerrufe', 'Statistik']],
    ]);

    it('is registered once: the panel does not put the widgets on a dashboard of their own', function () {
        foreach ([StatisticsOverview::class, StatisticsHistoryChart::class, SignalBreakdownTable::class] as $widget) {
            expect(Filament::getWidgets())->not->toContain($widget);
        }
    });
});

describe('period switch', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
        seedStatsFixture();
    });

    it('defaults to the month', function () {
        livewire(Statistics::class)->assertSet('filters.period', 'month');
    });

    it('takes the period from the URL', function () {
        $this->get('/admin/statistics?filters[period]=year')->assertOk();

        Livewire::withQueryParams(['filters' => ['period' => 'year']])->test(Statistics::class)->assertSet('filters.period', 'year');
        Livewire::withQueryParams(['filters' => ['period' => 'all']])->test(Statistics::class)->assertSet('filters.period', 'all');
    });

    it('wires the period to the widgets through Filament\'s page filters', function () {
        // Page::getWidgetsSchemaComponents() passes `$filters` as `pageFilters` to every widget of a page that has
        // the property; the widgets load lazily, so their content is asserted by mounting them (see "widgets").
        expect(property_exists(Statistics::class, 'filters'))->toBeTrue();

        foreach ([StatisticsOverview::class, StatisticsHistoryChart::class, SignalBreakdownTable::class] as $widget) {
            expect(property_exists($widget, 'pageFilters'))->toBeTrue();
        }

        $this->get('/admin/statistics?filters[period]=year')->assertOk();
        livewire(Statistics::class)->set('filters.period', 'year')->assertSet('filters.period', 'year');
    });
});

describe('widgets', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
        seedStatsFixture();
    });

    it('shows the six tiles per period', function () {
        expect(tilesOf('month'))->toBe(['Genuine' => 4, 'Suspected' => 2, 'Spam' => 7, 'Auto-sorted' => 4, 'False alarms' => 1, 'Missed' => 2])
            ->and(tilesOf('today'))->toBe(['Genuine' => 3, 'Suspected' => 2, 'Spam' => 5, 'Auto-sorted' => 4, 'False alarms' => 0, 'Missed' => 0])
            ->and(tilesOf('year'))->toMatchArray(['Genuine' => 141])
            ->and(tilesOf('all'))->toMatchArray(['Genuine' => 4383]);
    });

    it('formats the tile values for the panel locale', function () {
        $stat = fn (): mixed => collect((fn (): array => $this->getStats())->call(livewire(StatisticsOverview::class, ['pageFilters' => ['period' => 'all']])->instance()))->first()->getValue();

        expect($stat())->toBe('4,383');

        app()->setLocale('de');
        expect($stat())->toBe('4.383');
    });

    it('treats an unknown period like the default', function () {
        expect(tilesOf('decade'))->toMatchArray(['Genuine' => 4, 'Spam' => 7]);
    });

    it('stacks genuine, suspected and spam over the days of the month', function () {
        $widget = livewire(StatisticsHistoryChart::class, ['pageFilters' => ['period' => 'month']])->instance();
        $data = (fn (): array => $this->getData())->call($widget);

        expect((fn (): string => $this->getType())->call($widget))->toBe('bar')
            ->and($data['labels'])->toHaveCount(31)
            ->and(array_column($data['datasets'], 'label'))->toBe(['Genuine', 'Suspected', 'Spam'])
            ->and($data['datasets'][0]['data'][14])->toBe(3)
            ->and($data['datasets'][1]['data'][14])->toBe(2)
            ->and($data['datasets'][2]['data'][14])->toBe(5)
            ->and($data['datasets'][2]['data'][2])->toBe(2);
    });

    it('draws months for the year and years for all time', function () {
        $year = (fn (): array => $this->getData())->call(livewire(StatisticsHistoryChart::class, ['pageFilters' => ['period' => 'year']])->instance());
        $all = (fn (): array => $this->getData())->call(livewire(StatisticsHistoryChart::class, ['pageFilters' => ['period' => 'all']])->instance());

        expect($year['labels'])->toHaveCount(12)
            ->and($year['datasets'][0]['data'][2])->toBe(137)
            ->and($all['labels'])->toBe(['2025', '2026'])
            ->and($all['datasets'][0]['data'])->toBe([4242, 141]);
    });

    it('lists the signals of the period with their labels', function () {
        livewire(SignalBreakdownTable::class, ['pageFilters' => ['period' => 'month']])
            ->assertSee('Hidden honeypot field filled')
            ->assertSee('Too many submissions from one IP address')
            ->assertSee('Name without a space')
            ->assertSee('counted once per signal')
            ->assertSee('deleted after 7 days');
    });

    it('links a count to the matching tab with the signal and the period', function () {
        $expected = fn (string $tab, string $signal, array $range): string => e(WithdrawalResource::getUrl('index', [
            'tab' => $tab,
            'filters' => ['signal' => ['values' => [$signal]]] + $range,
        ]));
        $month = ['date_range' => ['from' => '2026-10-01', 'until' => '2026-10-31']];

        livewire(SignalBreakdownTable::class, ['pageFilters' => ['period' => 'month']])
            ->assertSeeHtml($expected('spam', 'honeypot', $month))
            ->assertSeeHtml($expected('inbox', 'honeypot', $month))
            ->assertSeeHtml($expected('spam', 'throttle', $month));

        livewire(SignalBreakdownTable::class, ['pageFilters' => ['period' => 'all']])
            ->assertSeeHtml($expected('spam', 'honeypot', []));
    });

    it('does not link a zero count', function () {
        $html = livewire(SignalBreakdownTable::class, ['pageFilters' => ['period' => 'month']])->html();

        // name_without_space has only a spam count: no Inbox link for it.
        $inboxLink = e(WithdrawalResource::getUrl('index', ['tab' => 'inbox', 'filters' => ['signal' => ['values' => ['name_without_space']]]]));

        expect($html)->not->toContain(substr($inboxLink, 0, (int) strpos($inboxLink, 'name_without_space') + 18));
    });

    it('shows the German labels in the German panel', function () {
        app()->setLocale('de');

        livewire(SignalBreakdownTable::class, ['pageFilters' => ['period' => 'month']])
            ->assertSee('Verstecktes Honeypot-Feld ausgefüllt')
            ->assertSee('pro Signal einmal gezählt');

        $labels = array_keys(tilesOf('month'));

        expect($labels)->toBe(['Echt', 'Verdächtig', 'Spam', 'Automatisch aussortiert', 'Fehlalarme', 'Übersehen']);
    });

    it('is empty without counters', function () {
        WithdrawalDailyCount::query()->delete();

        expect(tilesOf('month'))->toBe(['Genuine' => 0, 'Suspected' => 0, 'Spam' => 0, 'Auto-sorted' => 0, 'False alarms' => 0, 'Missed' => 0]);

        livewire(StatisticsHistoryChart::class, ['pageFilters' => ['period' => 'month']])
            ->assertSee('No withdrawals counted in this period');

        livewire(SignalBreakdownTable::class, ['pageFilters' => ['period' => 'month']])
            ->assertSee('No signal has fired in this period');
    });
});

describe('list links', function () {
    it('open the list on the stored rows that match', function () {
        $this->actingAs(User::factory()->create());
        Mail::fake();

        // The list's live preview shows the newest row of the tab whatever the filters say, so the match is the newest.
        Withdrawal::factory()->spam()->create(['spam_reason' => 'throttle', 'name' => 'Erika Musterfrau', 'created_at' => now()->subDay()]);
        Withdrawal::factory()->spam()->create(['spam_reason' => 'honeypot', 'name' => 'Max Mustermann']);
        Withdrawal::factory()->spam()->create(['spam_reason' => 'honeypot', 'name' => 'Hans Muster', 'created_at' => '2026-08-01 10:00:00']);

        $url = (fn (): string => $this->listUrl('spam', 'honeypot'))->call(livewire(SignalBreakdownTable::class, ['pageFilters' => ['period' => 'month']])->instance());

        expect($url)->toContain('tab=spam')->toContain('filters%5Bsignal%5D%5Bvalues%5D%5B0%5D=honeypot')->toContain('date_range');

        $this->get($url)
            ->assertOk()
            ->assertSee('Max Mustermann')
            ->assertDontSee('Erika Musterfrau')
            ->assertDontSee('Hans Muster');

        livewire(ListWithdrawals::class)->assertOk();
    });
});

describe('end to end', function () {
    it('shows what the counters record for a submit and a later verdict', function () {
        $this->actingAs(User::factory()->create());
        Mail::fake();

        submitCleanWithdrawal([
            'name' => 'Max Mustermann',
            'email' => 'kunde@example.com',
            'orderNumber' => 'A-12345',
            'subject' => 'Schlauchboot Modell X',
        ])->assertRedirect(route('withdrawal.success'));

        // The consumer submit switched the live locale; the operator panel runs in its own.
        app()->setLocale('en');

        expect(tilesOf('month'))->toMatchArray(['Genuine' => 1, 'Spam' => 0, 'Missed' => 0]);

        app(MarkWithdrawalAsSpam::class)->handle(Withdrawal::query()->firstOrFail());

        expect(tilesOf('month'))->toMatchArray(['Genuine' => 0, 'Spam' => 1, 'Missed' => 1]);

        livewire(Statistics::class)->assertOk()->assertSet('filters.period', 'month');
    });
});
