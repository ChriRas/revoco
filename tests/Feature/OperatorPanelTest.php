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
// app:operator command — options path
// ---------------------------------------------------------------------------

it('creates an operator user from --email / --password options', function () {
    $this->artisan('app:operator', [
        '--email' => 'test@example.com',
        '--password' => 'secret123',
    ])->assertSuccessful();

    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->email)->toBe('test@example.com');
});

it('is idempotent — running app:operator twice with options leaves exactly one operator', function () {
    $this->artisan('app:operator', [
        '--email' => 'operator@example.com',
        '--password' => 'secret123',
    ])->assertSuccessful();

    $this->artisan('app:operator', [
        '--email' => 'operator@example.com',
        '--password' => 'newpassword',
    ])->assertSuccessful();

    expect(User::where('email', 'operator@example.com')->count())->toBe(1);
});

it('hashes the operator password', function () {
    $this->artisan('app:operator', [
        '--email' => 'hash@example.com',
        '--password' => 'plaintext',
    ])->assertSuccessful();

    $user = User::where('email', 'hash@example.com')->firstOrFail();
    expect($user->password)->not->toBe('plaintext');
    expect(Hash::check('plaintext', $user->password))->toBeTrue();
});

it('preserves an existing operator name on update', function () {
    User::factory()->create(['email' => 'alice@example.com', 'name' => 'Alice']);

    $this->artisan('app:operator', [
        '--email' => 'alice@example.com',
        '--password' => 'newpassword',
    ])->assertSuccessful();

    $user = User::where('email', 'alice@example.com')->firstOrFail();
    expect($user->name)->toBe('Alice');
    expect(Hash::check('newpassword', $user->password))->toBeTrue();
});

it('assigns default name Operator on create when no --name is given', function () {
    $this->artisan('app:operator', [
        '--email' => 'op@example.com',
        '--password' => 'secret',
    ])->assertSuccessful();

    expect(User::where('email', 'op@example.com')->firstOrFail()->name)->toBe('Operator');
});

it('applies --name only on create', function () {
    $this->artisan('app:operator', [
        '--email' => 'named@example.com',
        '--password' => 'secret',
        '--name' => 'Boss',
    ])->assertSuccessful();

    expect(User::where('email', 'named@example.com')->firstOrFail()->name)->toBe('Boss');
});

it('rejects an invalid email format', function () {
    $this->artisan('app:operator', [
        '--email' => 'not-an-email',
        '--password' => 'secret',
    ])
        ->expectsOutputToContain('A valid e-mail address is required.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// app:operator command — interactive prompt path
// ---------------------------------------------------------------------------

it('prompts for email and password interactively when no options are given', function () {
    $this->artisan('app:operator')
        ->expectsQuestion('Operator e-mail address', 'prompted@example.com')
        ->expectsQuestion('Operator password', 'promptedpass')
        ->assertSuccessful();

    $user = User::where('email', 'prompted@example.com')->firstOrFail();
    expect(Hash::check('promptedpass', $user->password))->toBeTrue();
});

it('prompts for password interactively when only --email is given', function () {
    $this->artisan('app:operator', ['--email' => 'emailonly@example.com'])
        ->expectsQuestion('Operator password', 'fromPrompt')
        ->assertSuccessful();

    expect(User::where('email', 'emailonly@example.com')->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// app:operator command — non-interactive guard
// ---------------------------------------------------------------------------

it('fails cleanly under --no-interaction when --email is missing', function () {
    $this->artisan('app:operator', ['--no-interaction' => true])
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('fails cleanly under --no-interaction when --password is missing', function () {
    $this->artisan('app:operator', [
        '--email' => 'op@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::count())->toBe(0);
});
