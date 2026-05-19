<?php

use App\Enums\Currency;
use App\Enums\PlannedTransactionStatus;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('deleting a settling transaction reverts planned status to planned', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();
    $planned = PlannedTransaction::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create([
            'amount' => 100,
            'currency' => Currency::EUR,
            'status' => PlannedTransactionStatus::SETTLED,
        ]);
    $account = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);
    $txn = Transaction::factory()->for($planned, 'plannedTransaction')->create([
        'amount' => 100,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::CASH,
        'from_account_id' => $account->id,
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $txn))
        ->assertRedirect();

    expect(Transaction::count())->toBe(0)
        ->and($planned->fresh()->status)->toBe(PlannedTransactionStatus::PLANNED);
});

test('deleting a transaction on a transfer pair side reverts both planned sides', function () {
    $user = User::factory()->create();
    $a = Entity::factory()->llc()->for($user)->create();
    $b = Entity::factory()->personal()->for($user)->create();
    $aMirror = Counterparty::factory()->internal($a)->create();
    $bMirror = Counterparty::factory()->internal($b)->create();
    $group = (string) Str::uuid();

    $primary = PlannedTransaction::factory()
        ->for($a, 'ownerEntity')
        ->for($bMirror)
        ->outgoing()
        ->create(['amount' => 300, 'currency' => Currency::EUR, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::SETTLED]);

    $mirror = PlannedTransaction::factory()
        ->for($b, 'ownerEntity')
        ->for($aMirror)
        ->incoming()
        ->create(['amount' => 300, 'currency' => Currency::EUR, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::SETTLED]);

    $fromAccount = Account::factory()->for($a)->create(['currency' => Currency::EUR]);
    $toAccount = Account::factory()->for($b)->create(['currency' => Currency::EUR]);

    $txn = Transaction::factory()->for($primary, 'plannedTransaction')->create([
        'amount' => 300,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::BANK_TRANSFER,
        'from_account_id' => $fromAccount->id,
        'to_account_id' => $toAccount->id,
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $txn))
        ->assertRedirect();

    expect($primary->fresh()->status)->toBe(PlannedTransactionStatus::PLANNED)
        ->and($mirror->fresh()->status)->toBe(PlannedTransactionStatus::PLANNED);
});

test('foreign user cannot delete a transaction they do not own', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $entity = Entity::factory()->for($owner)->create();
    $account = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);

    $txn = Transaction::factory()->create([
        'planned_transaction_id' => null,
        'from_account_id' => $account->id,
        'amount' => 50,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::CASH,
    ]);

    $this->actingAs($intruder)
        ->delete(route('transactions.destroy', $txn))
        ->assertForbidden();

    expect(Transaction::count())->toBe(1);
});
