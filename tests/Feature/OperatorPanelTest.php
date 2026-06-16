<?php

declare(strict_types=1);

use App\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

it('redirects guests away from the admin panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('redirects guests away from admin login to a login page (not exposing data)', function () {
    $this->get('/admin/login')->assertOk();
});

it('allows an operator to access the admin panel', function () {
    $operator = User::factory()->create();
    // /admin redirects to the Withdrawals list (homeUrl) — follow the redirect
    $this->actingAs($operator)->get('/admin')->assertRedirectContains('/admin/withdrawals');
});

// ---------------------------------------------------------------------------
// List page: visible records
// ---------------------------------------------------------------------------

it('shows withdrawal records to the operator', function () {
    $operator = User::factory()->create();
    $withdrawals = Withdrawal::factory()->count(3)->create();

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->assertCanSeeTableRecords($withdrawals);
});

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

it('filters withdrawals by name search', function () {
    $operator = User::factory()->create();
    $match = Withdrawal::factory()->create(['name' => 'Max Mustermann']);
    $other = Withdrawal::factory()->create(['name' => 'Jane Doe']);

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->searchTable('Max')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

// ---------------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------------

it('filters withdrawals by handled status', function () {
    $operator = User::factory()->create();
    $handled = Withdrawal::factory()->handled()->create();
    $unhandled = Withdrawal::factory()->create();

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->filterTable('handled_at', '1')
        ->assertCanSeeTableRecords([$handled])
        ->assertCanNotSeeTableRecords([$unhandled]);
});

it('filters withdrawals by spam status', function () {
    $operator = User::factory()->create();
    $spam = Withdrawal::factory()->spam()->create();
    $clean = Withdrawal::factory()->create();

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->filterTable('spam', '1')
        ->assertCanSeeTableRecords([$spam])
        ->assertCanNotSeeTableRecords([$clean]);
});

// ---------------------------------------------------------------------------
// Handled toggle action
// ---------------------------------------------------------------------------

it('marks a withdrawal as handled via the table action', function () {
    $operator = User::factory()->create();
    $withdrawal = Withdrawal::factory()->create(['handled_at' => null]);

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->callTableAction('toggle_handled', $withdrawal);

    expect($withdrawal->fresh()->handled_at)->not->toBeNull();
});

it('clears handled_at when toggled again', function () {
    $operator = User::factory()->create();
    $withdrawal = Withdrawal::factory()->handled()->create();

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->callTableAction('toggle_handled', $withdrawal);

    expect($withdrawal->fresh()->handled_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Read-only — no create/edit/delete actions
// ---------------------------------------------------------------------------

it('has no create action on the list page', function () {
    $operator = User::factory()->create();
    Withdrawal::factory()->count(2)->create();

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->assertActionDoesNotExist('create');
});

it('has no edit action on the list page', function () {
    $operator = User::factory()->create();
    $withdrawal = Withdrawal::factory()->create();

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->assertTableActionDoesNotExist('edit', record: $withdrawal);
});

it('has no delete action on the list page', function () {
    $operator = User::factory()->create();
    $withdrawal = Withdrawal::factory()->create();

    $this->actingAs($operator);

    livewire(ListWithdrawals::class)
        ->assertTableActionDoesNotExist('delete', record: $withdrawal);
});

// ---------------------------------------------------------------------------
// app:operator command
// ---------------------------------------------------------------------------

it('creates an operator user from env when running app:operator', function () {
    config(['operator.email' => 'test@example.com', 'operator.password' => 'secret123']);

    $this->artisan('app:operator')->assertSuccessful();

    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->email)->toBe('test@example.com');
});

it('is idempotent — running app:operator twice leaves exactly one operator', function () {
    config(['operator.email' => 'operator@example.com', 'operator.password' => 'secret123']);

    $this->artisan('app:operator')->assertSuccessful();
    $this->artisan('app:operator')->assertSuccessful();

    $count = User::where('email', 'operator@example.com')->count();
    expect($count)->toBe(1);
});

it('hashes the operator password', function () {
    config(['operator.email' => 'hash@example.com', 'operator.password' => 'plaintext']);

    $this->artisan('app:operator')->assertSuccessful();

    $user = User::where('email', 'hash@example.com')->firstOrFail();
    expect($user->password)->not->toBe('plaintext');
    expect(Hash::check('plaintext', $user->password))->toBeTrue();
});

it('fails gracefully when OPERATOR_EMAIL is missing', function () {
    config(['operator.email' => '', 'operator.password' => 'secret']);

    $this->artisan('app:operator')->assertFailed();
});

it('fails gracefully when OPERATOR_PASSWORD is missing', function () {
    config(['operator.email' => 'operator@example.com', 'operator.password' => '']);

    $this->artisan('app:operator')->assertFailed();
});
