<?php

use App\Enums\PlannedTransactionStatus;
use App\Events\TransactionCreated;
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
            'status' => PlannedTransactionStatus::PLANNED,
            ...$overrides,
        ]);
}

test('guests cannot record transactions', function () {
    $planned = PlannedTransaction::factory()->create();

    $this->post(route('planned-transactions.transactions.store', $planned), [
        'amount' => 100,
        'occurred_on' => '2026-05-18',
    ])->assertRedirect(route('login'));
});

test('owner can record a transaction with explicit date', function () {
    Event::fake([TransactionCreated::class]);

    $user = User::factory()->create();
    $planned = plannedFor($user, 1000);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 250.50,
            'occurred_on' => '2026-05-10',
            'note' => 'First instalment',
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::first())
        ->planned_transaction_id->toBe($planned->id)
        ->amount->toBe('250.50')
        ->occurred_on->toDateString()->toBe('2026-05-10')
        ->note->toBe('First instalment');

    Event::assertDispatched(TransactionCreated::class);
});

test('occurred_on defaults to today when not provided', function () {
    Event::fake([TransactionCreated::class]);

    $user = User::factory()->create();
    $planned = plannedFor($user, 1000);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 100,
        ])
        ->assertRedirect();

    expect(Transaction::first()->occurred_on->toDateString())->toBe(now()->toDateString());
});

test('amount cannot exceed planned amount', function () {
    $user = User::factory()->create();
    $planned = plannedFor($user, 100);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 150,
            'occurred_on' => '2026-05-10',
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0);
});

test('amount cannot exceed remaining after a partial settlement', function () {
    $user = User::factory()->create();
    $planned = plannedFor($user, 100);

    Transaction::factory()->for($planned, 'plannedTransaction')->create(['amount' => 60]);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 50,
            'occurred_on' => '2026-05-10',
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(1);
});

test('partial settlements add up and a final one flips planned to settled', function () {
    Event::fake([TransactionCreated::class]);

    $user = User::factory()->create();
    $planned = plannedFor($user, 100);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 30,
            'occurred_on' => '2026-05-10',
        ])
        ->assertRedirect();

    expect($planned->fresh()->status)->toBe(PlannedTransactionStatus::PLANNED);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 70,
            'occurred_on' => '2026-05-11',
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(2)
        ->and($planned->fresh()->status)->toBe(PlannedTransactionStatus::SETTLED);
});

test('amount must be positive', function () {
    $user = User::factory()->create();
    $planned = plannedFor($user, 100);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 0,
            'occurred_on' => '2026-05-10',
        ])
        ->assertSessionHasErrors('amount');
});

test('a user cannot record on another users planned transaction', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $planned = plannedFor($owner, 100);

    $this->actingAs($intruder)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 50,
            'occurred_on' => '2026-05-10',
        ])
        ->assertForbidden();

    expect(Transaction::count())->toBe(0);
});

test('cannot record on a cancelled planned transaction', function () {
    $user = User::factory()->create();
    $planned = plannedFor($user, 100, ['status' => PlannedTransactionStatus::CANCELLED]);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $planned), [
            'amount' => 50,
            'occurred_on' => '2026-05-10',
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0);
});

test('recording on one side of a transfer pair mirrors the transaction on the other', function () {
    Event::fake([TransactionCreated::class]);

    $user = User::factory()->create();
    $a = Entity::factory()->llc()->for($user)->create(['name' => 'My LLC']);
    $b = Entity::factory()->personal()->for($user)->create(['name' => 'Me']);

    $aMirror = Counterparty::factory()->internal($a)->create();
    $bMirror = Counterparty::factory()->internal($b)->create();

    $group = (string) Str::uuid();

    $primary = PlannedTransaction::factory()
        ->for($a, 'ownerEntity')
        ->for($bMirror)
        ->outgoing()
        ->create(['amount' => 500, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::PLANNED]);

    $mirror = PlannedTransaction::factory()
        ->for($b, 'ownerEntity')
        ->for($aMirror)
        ->incoming()
        ->create(['amount' => 500, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::PLANNED]);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $primary), [
            'amount' => 200,
            'occurred_on' => '2026-05-10',
        ])
        ->assertRedirect();

    expect(Transaction::where('planned_transaction_id', $primary->id)->count())->toBe(1)
        ->and(Transaction::where('planned_transaction_id', $mirror->id)->count())->toBe(1);

    Event::assertDispatched(
        TransactionCreated::class,
        fn (TransactionCreated $event) => count($event->rows) === 2,
    );
});

test('filling a transfer pair fully flips both planned rows to settled', function () {
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
        ->create(['amount' => 300, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::PLANNED]);

    $mirror = PlannedTransaction::factory()
        ->for($b, 'ownerEntity')
        ->for($aMirror)
        ->incoming()
        ->create(['amount' => 300, 'transfer_group_id' => $group, 'status' => PlannedTransactionStatus::PLANNED]);

    $this->actingAs($user)
        ->post(route('planned-transactions.transactions.store', $primary), [
            'amount' => 300,
            'occurred_on' => '2026-05-10',
        ])
        ->assertRedirect();

    expect($primary->fresh()->status)->toBe(PlannedTransactionStatus::SETTLED)
        ->and($mirror->fresh()->status)->toBe(PlannedTransactionStatus::SETTLED);
});
