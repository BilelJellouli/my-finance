<?php

use App\Enums\Currency;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner sees their account ledger with running balance', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->for($user)->create();
    $account = Account::factory()->for($entity)->create([
        'currency' => Currency::EUR,
        'amount' => 1000,
    ]);

    Transaction::factory()->create([
        'planned_transaction_id' => null,
        'from_account_id' => null,
        'to_account_id' => $account->id,
        'amount' => 200,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::BANK_TRANSFER,
        'occurred_on' => '2026-05-10',
    ]);

    Transaction::factory()->create([
        'planned_transaction_id' => null,
        'from_account_id' => $account->id,
        'to_account_id' => null,
        'amount' => 50,
        'currency' => Currency::EUR,
        'kind' => TransactionKind::CASH,
        'occurred_on' => '2026-05-12',
    ]);

    $response = $this->actingAs($user)->get(route('accounts.show', $account));

    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('accounts/Show')
        ->where('account.id', $account->id)
        ->where('account.opening_balance', '1000.00')
        ->where('account.current_balance', '1150.00')
        ->has('ledger', 2)
        // Display is descending — newest first.
        ->where('ledger.0.direction', 'outgoing')
        ->where('ledger.0.signed_amount', '-50.00')
        ->where('ledger.0.running_balance', '1150.00')
        ->where('ledger.1.direction', 'incoming')
        ->where('ledger.1.signed_amount', '200.00')
        ->where('ledger.1.running_balance', '1200.00')
    );
});

test('foreign user cannot view another users account ledger', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $entity = Entity::factory()->for($owner)->create();
    $account = Account::factory()->for($entity)->create();

    $this->actingAs($intruder)
        ->get(route('accounts.show', $account))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    $entity = Entity::factory()->create();
    $account = Account::factory()->for($entity)->create();

    $this->get(route('accounts.show', $account))->assertRedirect(route('login'));
});
