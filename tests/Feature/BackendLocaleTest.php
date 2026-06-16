<?php

declare(strict_types=1);

use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Default locale (en) — English labels, no German resource labels in the panel
// ---------------------------------------------------------------------------

it('renders English panel labels when BACKEND_LOCALE is unset (default en)', function () {
    $operator = User::factory()->create();
    Withdrawal::factory()->create();

    app()->setLocale('en');
    config(['operator.locale' => 'en']);

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->assertSee('Received')        // panel.column.received (en)
        ->assertSee('No Spam')         // panel.column.no_spam (en)
        ->assertDontSee('Widerruf');   // must not leak German resource label
});

it('renders English Filament chrome under en locale', function () {
    $operator = User::factory()->create();
    Withdrawal::factory()->create();

    app()->setLocale('en');
    config(['operator.locale' => 'en']);

    $this->actingAs($operator);

    // The Filament table search field label is a reliable chrome string.
    livewire(ListWithdrawals::class)
        ->assertSee('Search')          // filament::table.fields.search.label (en)
        ->assertDontSee('Suche');      // must not see German chrome word
});

// ---------------------------------------------------------------------------
// German variant (BACKEND_LOCALE=de)
// ---------------------------------------------------------------------------

it('renders German panel labels when BACKEND_LOCALE=de', function () {
    $operator = User::factory()->create();
    Withdrawal::factory()->create();

    app()->setLocale('de');
    config(['operator.locale' => 'de']);

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->assertSee('Widerruf')        // panel.resource.plural_model_label (de)
        ->assertSee('Eingegangen')     // panel.column.received (de)
        ->assertDontSee('Received');   // must not see English column label
});

it('renders German Filament chrome under de locale', function () {
    $operator = User::factory()->create();
    Withdrawal::factory()->create();

    app()->setLocale('de');
    config(['operator.locale' => 'de']);

    $this->actingAs($operator);

    // The Filament table search field placeholder is a reliable chrome string.
    livewire(ListWithdrawals::class)
        ->assertSee('Suche');          // filament::table.fields.search.placeholder (de)
});

// ---------------------------------------------------------------------------
// Consumer form unaffected — APP_LOCALE=de, form still German
// ---------------------------------------------------------------------------

it('serves the consumer withdrawal form in German regardless of BACKEND_LOCALE', function () {
    // Set BACKEND_LOCALE=en (operator panel would be English).
    config(['operator.locale' => 'en']);

    // APP_LOCALE stays de — the consumer form is driven by the global locale.
    // The consumer route does NOT go through SetBackendLocale middleware.
    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertSee('Widerrufsformular');   // wf.title (de)
});

// ---------------------------------------------------------------------------
// Persistence across Livewire interaction — locale holds after a table event
// ---------------------------------------------------------------------------

it('maintains the German locale after a Livewire table search interaction', function () {
    $operator = User::factory()->create();
    Withdrawal::factory()->create(['name' => 'Max Mustermann']);

    // Simulate the persistent middleware having set the locale to de.
    app()->setLocale('de');
    config(['operator.locale' => 'de']);

    $this->actingAs($operator);

    // Perform a search table action (Livewire AJAX equivalent in tests).
    livewire(ListWithdrawals::class)
        ->searchTable('Max')
        ->assertSee('Widerruf')      // panel label still German after interaction
        ->assertSee('Eingegangen');  // column still German after interaction
});

it('maintains the German locale after a Livewire table filter interaction', function () {
    $operator = User::factory()->create();
    Withdrawal::factory()->handled()->create();
    Withdrawal::factory()->create();

    app()->setLocale('de');
    config(['operator.locale' => 'de']);

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->filterTable('handled_at', '1')
        ->assertSee('Widerruf')      // panel label still German after filter interaction
        ->assertSee('Eingegangen');  // column still German after filter interaction
});
