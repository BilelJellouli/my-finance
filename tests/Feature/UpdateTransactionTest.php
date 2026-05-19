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

uses(RefreshDatabase::class);

function buildPlannedAndAccount(User $user, float $plannedAmount = 100.00): array
{
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();
    $planned = PlannedTransaction::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create([
            'amount' => $plannedAmount,
            'currency' => Currency::EUR,
            'status' => PlannedTransactionStatus::PLANNED,
        ]);
    $account = Account::factory()->for($entity)->create(['currency' => Currency::EUR]);

    return [$planned, $account];
}

test('editing an amount re-checks the remaining cap on the planned row', function () {
    $user = User::factory()->create();
    [$planned, $account] = buildPlannedAndAccount($user, 100);

    $txn = Transaction::factory()->for($planned, 'plannedTransaction')->create([
        'amount' => 40,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::CASH,
        'from_account_id' => $account->id,
    ]);

    Transaction::factory()->for($planned, 'plannedTransaction')->create([
        'amount' => 50,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::CASH,
        'from_account_id' => $account->id,
    ]);

    $this->actingAs($user)
        ->put(route('transactions.update', $txn), [
            'from_account_id' => $account->id,
            'amount' => 80,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertSessionHasErrors('amount');
});

test('reducing a settling transaction reverts planned status back to planned', function () {
    $user = User::factory()->create();
    [$planned, $account] = buildPlannedAndAccount($user, 100);

    $txn = Transaction::factory()->for($planned, 'plannedTransaction')->create([
        'amount' => 100,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::CASH,
        'from_account_id' => $account->id,
    ]);
    $planned->update(['status' => PlannedTransactionStatus::SETTLED]);

    $this->actingAs($user)
        ->put(route('transactions.update', $txn), [
            'from_account_id' => $account->id,
            'amount' => 40,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertRedirect();

    expect($planned->fresh()->status)->toBe(PlannedTransactionStatus::PLANNED);
});

test('foreign user cannot update a transaction they do not own', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    [$planned, $account] = buildPlannedAndAccount($owner, 100);

    $txn = Transaction::factory()->for($planned, 'plannedTransaction')->create([
        'amount' => 50,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::CASH,
        'from_account_id' => $account->id,
    ]);

    $this->actingAs($intruder)
        ->put(route('transactions.update', $txn), [
            'from_account_id' => $account->id,
            'amount' => 60,
            'occurred_on' => '2026-05-10',
            'kind' => TransactionKind::CASH->value,
            'currency' => Currency::EUR->value,
        ])
        ->assertForbidden();
});
