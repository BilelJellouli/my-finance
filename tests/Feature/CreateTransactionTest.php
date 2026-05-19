<?php

use App\Enums\Currency;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('standalone outgoing transaction persists with kind and currency', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $account = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $account->id,
            'amount' => 42.99,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CARD->value,
            'currency' => Currency::EUR->value,
            'note' => 'Groceries',
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::first())
        ->planned_transaction_id->toBeNull()
        ->from_account_id->toBe($account->id)
        ->to_account_id->toBeNull()
        ->kind->toBe(TransactionKind::CARD)
        ->currency->toBe(Currency::EUR)
        ->note->toBe('Groceries');
});

test('standalone incoming transaction with external counterparty', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $account = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);
    $counterparty = Counterparty::factory()->external()->for($user)->create(['name' => 'ACME Corp']);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'to_account_id' => $account->id,
            'counterparty_id' => $counterparty->id,
            'amount' => 1500,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::BANK_TRANSFER->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect(Transaction::first())
        ->from_account_id->toBeNull()
        ->to_account_id->toBe($account->id)
        ->counterparty_id->toBe($counterparty->id);
});

test('same-entity account-to-account transfer persists with both accounts set', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $bank = Account::factory()->for($entity)->create(['name' => 'Bank', 'currency' => Currency::EUR]);
    $cash = Account::factory()->for($entity)->create(['name' => 'Cash', 'currency' => Currency::EUR]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $bank->id,
            'to_account_id' => $cash->id,
            'amount' => 200,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect(Transaction::first())
        ->from_account_id->toBe($bank->id)
        ->to_account_id->toBe($cash->id)
        ->kind->toBe(TransactionKind::CASH);
});

test('rejects when no account and no planned transaction provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'amount' => 100,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('from_account_id');

    expect(Transaction::count())->toBe(0);
});

test('rejects cross-currency transfer between accounts', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $eur = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);
    $usd = Account::factory()->for($entity)->create(['currency' => Currency::USD]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $eur->id,
            'to_account_id' => $usd->id,
            'amount' => 100,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::BANK_TRANSFER->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('currency');

    expect(Transaction::count())->toBe(0);
});

test('rejects unknown kind', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $account = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $account->id,
            'amount' => 100,
            'occurred_on' => '2026-05-10',
            'kind' => 'crypto',
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('kind');
});

test('rejects from and to being the same account', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $account = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => 50,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('to_account_id');
});

test('rejects when account belongs to another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ownerEntity = Entity::factory()->for($owner)->create();
    $ownerAccount = Account::factory()->for($ownerEntity)->create(['currency' => Currency::EUR]);

    $this->actingAs($intruder)
        ->post(route('transactions.store'), [
            'from_account_id' => $ownerAccount->id,
            'amount' => 100,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('from_account_id');

    expect(Transaction::count())->toBe(0);
});

test('rejects when source account has insufficient balance', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $account = Account::factory()->for($entity)->create([
        'currency' => Currency::EUR,
        'amount' => 50,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $account->id,
            'amount' => 100,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0);
});

test('balance enforcement accounts for previously-recorded outgoing transactions', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $account = Account::factory()->for($entity)->create([
        'currency' => Currency::EUR,
        'amount' => 100,
    ]);

    Transaction::factory()->create([
        'planned_transaction_id' => null,
        'from_account_id' => $account->id,
        'to_account_id' => null,
        'amount' => 80,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::CASH,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $account->id,
            'amount' => 30,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('amount');
});

test('balance enforcement credits previous incoming transactions to the account', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $account = Account::factory()->for($entity)->create([
        'currency' => Currency::EUR,
        'amount' => 50,
    ]);

    Transaction::factory()->create([
        'planned_transaction_id' => null,
        'from_account_id' => null,
        'to_account_id' => $account->id,
        'amount' => 200,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::BANK_TRANSFER,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $account->id,
            'amount' => 240,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(2);
});

test('rejects when transaction currency does not match account currency', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $eur = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'from_account_id' => $eur->id,
            'amount' => 100,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::USD->value,
        ])
        ->assertSessionHasErrors('currency');
});
