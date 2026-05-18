<?php

use App\Actions\UpdatePlannedTransaction;
use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Events\PlannedTransactionUpdated;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function updatePlannedTransactionPayload(array $overrides = []): array
{
    return [
        'amount' => 1500.25,
        'currency' => 'EUR',
        'due_date' => '2026-07-15',
        'purpose' => 'Updated rent',
        'status' => 'settled',
        'is_mandatory' => false,
        'note' => 'Adjusted after lease review',
        ...$overrides,
    ];
}

test('guests cannot update planned transactions', function () {
    $txn = PlannedTransaction::factory()->create();

    $this->put(route('planned-transactions.update', $txn), updatePlannedTransactionPayload())
        ->assertRedirect(route('login'));
});

test('owner can update a single-row planned transaction', function () {
    Event::fake([PlannedTransactionUpdated::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $counterparty = Counterparty::factory()->external()->for($user)->create();
    $txn = PlannedTransaction::factory()->for($entity, 'ownerEntity')->for($counterparty)->create([
        'amount' => 100,
        'currency' => Currency::TND,
        'status' => PlannedTransactionStatus::PLANNED,
        'is_mandatory' => true,
    ]);

    $this->actingAs($user)
        ->put(route('planned-transactions.update', $txn), updatePlannedTransactionPayload())
        ->assertRedirect();

    expect($txn->fresh())
        ->amount->toBe('1500.25')
        ->currency->toBe(Currency::EUR)
        ->due_date->toDateString()->toBe('2026-07-15')
        ->purpose->toBe('Updated rent')
        ->status->toBe(PlannedTransactionStatus::SETTLED)
        ->is_mandatory->toBeFalse()
        ->note->toBe('Adjusted after lease review');

    Event::assertDispatched(
        PlannedTransactionUpdated::class,
        fn (PlannedTransactionUpdated $event) => count($event->rows) === 1
            && $event->rows[0]->id === $txn->id,
    );
});

test('updating one row of a transfer pair updates both atomically', function () {
    Event::fake([PlannedTransactionUpdated::class]);

    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create(['name' => 'My LLC']);
    $personal = Entity::factory()->personal()->for($user)->create(['name' => 'Me']);

    $llcMirror = Counterparty::factory()->internal($llc)->create();
    $personalMirror = Counterparty::factory()->internal($personal)->create();

    $group = (string) Str::uuid();

    $primary = PlannedTransaction::factory()
        ->for($llc, 'ownerEntity')
        ->for($personalMirror)
        ->outgoing()
        ->create([
            'amount' => 500,
            'transfer_group_id' => $group,
            'status' => PlannedTransactionStatus::PLANNED,
        ]);

    $mirror = PlannedTransaction::factory()
        ->for($personal, 'ownerEntity')
        ->for($llcMirror)
        ->incoming()
        ->create([
            'amount' => 500,
            'transfer_group_id' => $group,
            'status' => PlannedTransactionStatus::PLANNED,
        ]);

    $this->actingAs($user)
        ->put(route('planned-transactions.update', $primary), updatePlannedTransactionPayload([
            'amount' => 750,
            'currency' => 'TND',
            'status' => 'settled',
            'purpose' => 'Owner draw — revised',
        ]))
        ->assertRedirect();

    expect($primary->fresh())
        ->amount->toBe('750.00')
        ->currency->toBe(Currency::TND)
        ->status->toBe(PlannedTransactionStatus::SETTLED)
        ->purpose->toBe('Owner draw — revised')
        ->direction->toBe(PlannedTransactionDirection::OUTGOING);

    expect($mirror->fresh())
        ->amount->toBe('750.00')
        ->currency->toBe(Currency::TND)
        ->status->toBe(PlannedTransactionStatus::SETTLED)
        ->purpose->toBe('Owner draw — revised')
        ->direction->toBe(PlannedTransactionDirection::INCOMING);

    Event::assertDispatched(
        PlannedTransactionUpdated::class,
        fn (PlannedTransactionUpdated $event) => count($event->rows) === 2,
    );
});

test('a user cannot update another users planned transaction', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $entity = Entity::factory()->llc()->for($owner)->create();
    $txn = PlannedTransaction::factory()->for($entity, 'ownerEntity')->create([
        'amount' => 100,
    ]);

    $this->actingAs($intruder)
        ->put(route('planned-transactions.update', $txn), updatePlannedTransactionPayload())
        ->assertForbidden();

    expect($txn->fresh()->amount)->toBe('100.00');
});

test('update validates required and typed fields', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $txn = PlannedTransaction::factory()->for($entity, 'ownerEntity')->create();

    $this->actingAs($user)
        ->put(route('planned-transactions.update', $txn), [
            'amount' => -5,
            'currency' => 'XYZ',
            'status' => 'mystery',
            'is_mandatory' => 'not-a-bool',
        ])
        ->assertSessionHasErrors(['amount', 'currency', 'status', 'is_mandatory']);
});

test('UpdatePlannedTransaction action mirrors edits to the paired row', function () {
    Event::fake([PlannedTransactionUpdated::class]);

    $user = User::factory()->create();
    $a = Entity::factory()->llc()->for($user)->create();
    $b = Entity::factory()->personal()->for($user)->create();
    $aMirror = Counterparty::factory()->internal($a)->create();
    $bMirror = Counterparty::factory()->internal($b)->create();
    $group = (string) Str::uuid();

    $primary = PlannedTransaction::factory()->for($a, 'ownerEntity')->for($bMirror)->outgoing()->create([
        'transfer_group_id' => $group,
        'amount' => 200,
    ]);
    $mirror = PlannedTransaction::factory()->for($b, 'ownerEntity')->for($aMirror)->incoming()->create([
        'transfer_group_id' => $group,
        'amount' => 200,
    ]);

    $rows = app(UpdatePlannedTransaction::class)->execute(
        plannedTransaction: $primary,
        amount: 333.33,
        currency: Currency::USD,
        dueDate: '2026-09-01',
        purpose: 'Sync test',
        status: PlannedTransactionStatus::OVERDUE,
        isMandatory: true,
        note: null,
    );

    expect($rows)->toHaveCount(2);

    expect($mirror->fresh())
        ->amount->toBe('333.33')
        ->currency->toBe(Currency::USD)
        ->status->toBe(PlannedTransactionStatus::OVERDUE);

    Event::assertDispatched(PlannedTransactionUpdated::class);
});
