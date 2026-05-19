<?php

use App\Enums\Currency;
use App\Enums\PlannedTransactionStatus;
use App\Enums\TransactionKind;
use App\Events\TransactionCreated;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function plannedFor(User $user, float $amount = 1000.00, array $overrides = []): PlannedTransaction
{
    $entity = Entity::factory()->llc()->for($user)->create();
    $counterparty = Counterparty::factory()->external()->for($user)->create();

    return PlannedTransaction::factory()
        ->for($entity, 'ownerEntity')
        ->for($counterparty)
        ->create([
            'amount' => $amount,
            'currency' => Currency::EUR,
            'status' => PlannedTransactionStatus::PLANNED,
            ...$overrides,
        ]);
}

function accountFor(User $user, Currency $currency = Currency::EUR): Account
{
    $entity = Entity::factory()->llc()->for($user)->create();

    return Account::factory()->for($entity)->create([
        'currency' => $currency,
    ]);
}

test('guests cannot record transactions', function () {
    $planned = PlannedTransaction::factory()->create();

    $this->post(route('transactions.store'), [
        'planned_transaction_id' => $planned->id,
        'amount' => 100,
        'occurred_on' => '2026-05-18',
        'kind' => TransactionKind::CASH->value,
        'currency' => Currency::EUR->value,
    ])->assertRedirect(route('login'));
});

test('owner can record a transaction against a planned row with explicit date', function () {
    Event::fake([TransactionCreated::class]);

    $user = User::factory()->create();
    $planned = plannedFor($user, 1000);
    $account = accountFor($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $account->id,
            'amount' => 250.50,
            'occurred_on' => '2026-05-10',
            'note' => 'First instalment',
            'kind' => TransactionKind::BANK_TRANSFER->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::first())
        ->planned_transaction_id->toBe($planned->id)
        ->from_account_id->toBe($account->id)
        ->to_account_id->toBeNull()
        ->amount->toBe('250.50')
        ->occurred_on->toDateString()->toBe('2026-05-10')
        ->note->toBe('First instalment')
        ->kind->toBe(TransactionKind::BANK_TRANSFER);

    Event::assertDispatched(TransactionCreated::class);
});

test('occurred_on defaults to today when not provided', function () {
    Event::fake([TransactionCreated::class]);

    $user = User::factory()->create();
    $planned = plannedFor($user, 1000);
    $account = accountFor($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $account->id,
            'amount' => 100,
            'kind' => TransactionKind::CARD->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect(Transaction::first()->occurred_on->toDateString())->toBe(now()->toDateString());
});

test('amount cannot exceed planned amount', function () {
    $user = User::factory()->create();
    $planned = plannedFor($user, 100);
    $account = accountFor($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $account->id,
            'amount' => 150,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0);
});

test('amount cannot exceed remaining after a partial settlement', function () {
    $user = User::factory()->create();
    $planned = plannedFor($user, 100);
    $account = accountFor($user);

    Transaction::factory()->for($planned, 'plannedTransaction')->create([
        'amount' => 60,
        'currency' => Currency::EUR,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $account->id,
            'amount' => 50,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(1);
});

test('partial settlements add up and a final one flips planned to settled', function () {
    Event::fake([TransactionCreated::class]);

    $user = User::factory()->create();
    $planned = plannedFor($user, 100);
    $account = accountFor($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $account->id,
            'amount' => 30,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect($planned->fresh()->status)->toBe(PlannedTransactionStatus::PLANNED);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $account->id,
            'amount' => 70,
            'occurred_on' => '2026-05-11',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(2)
        ->and($planned->fresh()->status)->toBe(PlannedTransactionStatus::SETTLED);
});

test('amount must be positive', function () {
    $user = User::factory()->create();
    $planned = plannedFor($user, 100);
    $account = accountFor($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $account->id,
            'amount' => 0,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('amount');
});

test('a user cannot record on another users planned transaction', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $planned = plannedFor($owner, 100);
    $intruderAccount = accountFor($intruder);

    $this->actingAs($intruder)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $intruderAccount->id,
            'amount' => 50,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertForbidden();

    expect(Transaction::count())->toBe(0);
});

test('cannot record on a cancelled planned transaction', function () {
    $user = User::factory()->create();
    $planned = plannedFor($user, 100, ['status' => PlannedTransactionStatus::CANCELLED]);
    $account = accountFor($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $planned->id,
            'from_account_id' => $account->id,
            'amount' => 50,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0);
});

test('recording on one side of a transfer pair creates a single transaction and flips both planned rows when settled', function () {
    Event::fake([TransactionCreated::class]);

    $user = User::factory()->create();
    $a = Entity::factory()->llc()->for($user)->create(['name' => 'My LLC']);
    $b = Entity::factory()->personal()->for($user)->create(['name' => 'Me']);

    $aMirror = Counterparty::factory()->internal($a)->create();
    $bMirror = Counterparty::factory()->internal($b)->create();

    $fromAccount = Account::factory()->for($a)->create(['currency' => Currency::EUR]);
    $toAccount = Account::factory()->for($b)->create(['currency' => Currency::EUR]);

    $group = (string) Str::uuid();

    $primary = PlannedTransaction::factory()
        ->for($a, 'ownerEntity')
        ->for($bMirror)
        ->outgoing()
        ->create(['amount' => 500, 'currency' => Currency::EUR, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::PLANNED]);

    $mirror = PlannedTransaction::factory()
        ->for($b, 'ownerEntity')
        ->for($aMirror)
        ->incoming()
        ->create(['amount' => 500, 'currency' => Currency::EUR, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::PLANNED]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $primary->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 200,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::BANK_TRANSFER->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::where('planned_transaction_id', $primary->id)->count())->toBe(1)
        ->and(Transaction::where('planned_transaction_id', $mirror->id)->count())->toBe(0);

    Event::assertDispatched(TransactionCreated::class);
});

test('filling a transfer pair fully flips both planned rows to settled', function () {
    $user = User::factory()->create();
    $a = Entity::factory()->llc()->for($user)->create();
    $b = Entity::factory()->personal()->for($user)->create();
    $aMirror = Counterparty::factory()->internal($a)->create();
    $bMirror = Counterparty::factory()->internal($b)->create();
    $group = (string) Str::uuid();

    $fromAccount = Account::factory()->for($a)->create(['currency' => Currency::EUR]);
    $toAccount = Account::factory()->for($b)->create(['currency' => Currency::EUR]);

    $primary = PlannedTransaction::factory()
        ->for($a, 'ownerEntity')
        ->for($bMirror)
        ->outgoing()
        ->create(['amount' => 300, 'currency' => Currency::EUR, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::PLANNED]);

    $mirror = PlannedTransaction::factory()
        ->for($b, 'ownerEntity')
        ->for($aMirror)
        ->incoming()
        ->create(['amount' => 300, 'currency' => Currency::EUR, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::PLANNED]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'planned_transaction_id' => $primary->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => 300,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::BANK_TRANSFER->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(1)
        ->and($primary->fresh()->status)->toBe(PlannedTransactionStatus::SETTLED)
        ->and($mirror->fresh()->status)->toBe(PlannedTransactionStatus::SETTLED);
});
