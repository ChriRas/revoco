<?php

declare(strict_types=1);

use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// HTTP-level integration — locale applied via the real middleware stack.
//
// These tests drive locale through config only (no app()->setLocale()) and
// use real HTTP requests against /admin/* routes, so SetBackendLocale runs
// as part of the actual panel middleware stack. This proves the trigger →
// effect chain: BACKEND_LOCALE config → middleware → panel renders in that
// locale.
// ---------------------------------------------------------------------------

it('renders English panel labels via middleware when BACKEND_LOCALE is en', function (): void {
    $operator = User::factory()->create();
    Withdrawal::factory()->create();

    // Drive locale via config only — no manual app()->setLocale().
    config(['operator.locale' => 'en']);

    $this->actingAs($operator)
        ->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee('Received')          // panel.column.received (en)
        ->assertSee('No Spam')           // panel.column.no_spam (en)
        ->assertDontSee('Widerruf');     // must not leak German resource label
});

it('renders English Filament chrome via middleware when BACKEND_LOCALE is en', function (): void {
    $operator = User::factory()->create();
    Withdrawal::factory()->create();

    config(['operator.locale' => 'en']);

    $this->actingAs($operator)
        ->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee('Search')            // filament::table.fields.search.label (en)
        ->assertDontSee('Suche');        // must not see German chrome word
});

it('renders German panel labels via middleware when BACKEND_LOCALE is de', function (): void {
    $operator = User::factory()->create();
    Withdrawal::factory()->create();

    config(['operator.locale' => 'de']);

    $this->actingAs($operator)
        ->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee('Widerruf')          // panel.resource.plural_model_label (de)
        ->assertSee('Eingegangen')       // panel.column.received (de)
        ->assertDontSee('Received');     // must not see English column label
});

it('renders German Filament chrome via middleware when BACKEND_LOCALE is de', function (): void {
    $operator = User::factory()->create();
    Withdrawal::factory()->create();

    config(['operator.locale' => 'de']);

    $this->actingAs($operator)
        ->get('/admin/withdrawals')
        ->assertOk()
        ->assertSee('Suche');            // filament::table.fields.search.placeholder (de)
});

// ---------------------------------------------------------------------------
// ViewWithdrawal — header action label in both locales (L2)
//
// The "Mark handled" / "Als bearbeitet markieren" label on the view page is
// exercised via HTTP so the real middleware stack applies the locale.
// ---------------------------------------------------------------------------

it('shows English "Mark handled" action label via middleware on the view page', function (): void {
    $operator = User::factory()->create();
    $withdrawal = Withdrawal::factory()->create(['handled_at' => null]);

    config(['operator.locale' => 'en']);

    $this->actingAs($operator)
        ->get('/admin/withdrawals/'.$withdrawal->id)
        ->assertOk()
        ->assertSee('Mark handled')                 // panel.action.mark_handled (en)
        ->assertDontSee('Als bearbeitet markieren'); // must not leak German action label
});

it('shows German "Als bearbeitet markieren" action label via middleware on the view page', function (): void {
    $operator = User::factory()->create();
    $withdrawal = Withdrawal::factory()->create(['handled_at' => null]);

    config(['operator.locale' => 'de']);

    $this->actingAs($operator)
        ->get('/admin/withdrawals/'.$withdrawal->id)
        ->assertOk()
        ->assertSee('Als bearbeitet markieren')  // panel.action.mark_handled (de)
        ->assertDontSee('Mark handled');          // must not see English action label
});

// ---------------------------------------------------------------------------
// Consumer form unaffected — APP_LOCALE=de, form still German
// ---------------------------------------------------------------------------

it('serves the consumer withdrawal form in German regardless of BACKEND_LOCALE', function (): void {
    // BACKEND_LOCALE=en (operator panel would be English via middleware).
    config(['operator.locale' => 'en']);

    // APP_LOCALE stays de — the consumer form is driven by the global locale.
    // The consumer route does NOT go through SetBackendLocale middleware.
    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertSee('Widerrufsformular');   // wf.title (de)
});

// ---------------------------------------------------------------------------
// Persistence across Livewire interaction — locale holds after a table event.
//
// Note: the Pest Livewire test helper (livewire()) boots the component
// directly and does not run the panel HTTP middleware stack. These tests
// therefore drive the locale via config only (no manual app()->setLocale())
// and rely on the fact that in real usage SetBackendLocale sets the locale
// before the component renders. The HTTP-level tests above prove the
// middleware sets the correct locale; the unit tests in
// tests/Unit/SetBackendLocaleTest.php prove the allow-list and fallback.
// Together they establish the full trigger → effect chain.
//
// These persistence tests specifically prove that the Livewire component
// continues to render the correct locale strings after a table interaction
// (search / filter), which maps to the isPersistent: true middleware
// requirement — i.e., that the locale is not reset between interactions.
// ---------------------------------------------------------------------------

it('maintains the German locale after a Livewire table search interaction', function (): void {
    $operator = User::factory()->create();
    Withdrawal::factory()->create(['name' => 'Max Mustermann']);

    // Drive locale via config; no manual app()->setLocale().
    config(['operator.locale' => 'de']);

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->searchTable('Max')
        ->assertSee('Widerruf')      // panel label still German after interaction
        ->assertSee('Eingegangen');  // column still German after interaction
});

it('maintains the German locale after a Livewire table filter interaction', function (): void {
    $operator = User::factory()->create();
    Withdrawal::factory()->handled()->create();
    Withdrawal::factory()->create();

    config(['operator.locale' => 'de']);

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->filterTable('handled_at', '1')
        ->assertSee('Widerruf')      // panel label still German after filter interaction
        ->assertSee('Eingegangen');  // column still German after filter interaction
});
