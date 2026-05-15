<?php

use App\Enums\CounterpartyKind;
use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('a planned transaction belongs to an owner entity and a counterparty', function () {
    $user = User::factory()->create();
    $owner = Entity::factory()->llc()->for($user)->create();
    $landlord = Counterparty::factory()->for($user)->create(['name' => 'Landlord']);

    $txn = PlannedTransaction::factory()
        ->outgoing()
        ->create([
            'owner_entity_id' => $owner->id,
            'counterparty_id' => $landlord->id,
            'amount' => '1200.00',
            'currency' => Currency::EUR,
            'due_date' => '2026-06-01',
            'purpose' => 'Rent',
        ]);

    expect($txn->ownerEntity->id)->toBe($owner->id)
        ->and($txn->counterparty->id)->toBe($landlord->id)
        ->and($txn->direction)->toBe(PlannedTransactionDirection::OUTGOING)
        ->and($txn->status)->toBe(PlannedTransactionStatus::PLANNED)
        ->and($txn->isOutgoing())->toBeTrue()
        ->and($txn->isPartOfTransfer())->toBeFalse();
});

test('an external counterparty has no entity link', function () {
    $user = User::factory()->create();
    $cp = Counterparty::factory()->external()->for($user)->create(['name' => 'ACME Client']);

    expect($cp->kind)->toBe(CounterpartyKind::EXTERNAL)
        ->and($cp->entity_id)->toBeNull()
        ->and($cp->isInternal())->toBeFalse()
        ->and($cp->displayName())->toBe('ACME Client');
});

test('an internal counterparty mirrors an entity', function () {
    $user = User::factory()->create();
    $personal = Entity::factory()->personal()->for($user)->create();
    $cp = Counterparty::factory()->internal($personal)->create();

    expect($cp->kind)->toBe(CounterpartyKind::INTERNAL)
        ->and($cp->entity_id)->toBe($personal->id)
        ->and($cp->isInternal())->toBeTrue()
        ->and($cp->displayName())->toBe($personal->name);
});

test('an entity has at most one internal counterparty mirror', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    Counterparty::factory()->internal($entity)->create();

    expect(fn () => Counterparty::factory()->internal($entity)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

test('an inter-entity transfer is two linked rows sharing a transfer_group_id', function () {
    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create();
    $personal = Entity::factory()->personal()->for($user)->create();

    $personalAsCounterparty = Counterparty::factory()->internal($personal)->create();
    $llcAsCounterparty = Counterparty::factory()->internal($llc)->create();

    $groupId = (string) Str::uuid();

    $outgoing = PlannedTransaction::factory()->create([
        'owner_entity_id' => $llc->id,
        'counterparty_id' => $personalAsCounterparty->id,
        'direction' => PlannedTransactionDirection::OUTGOING,
        'amount' => '500.00',
        'currency' => Currency::EUR,
        'due_date' => '2026-06-15',
        'transfer_group_id' => $groupId,
    ]);

    $incoming = PlannedTransaction::factory()->create([
        'owner_entity_id' => $personal->id,
        'counterparty_id' => $llcAsCounterparty->id,
        'direction' => PlannedTransactionDirection::INCOMING,
        'amount' => '500.00',
        'currency' => Currency::EUR,
        'due_date' => '2026-06-15',
        'transfer_group_id' => $groupId,
    ]);

    $pair = PlannedTransaction::where('transfer_group_id', $groupId)->get();

    expect($pair)->toHaveCount(2)
        ->and($outgoing->isPartOfTransfer())->toBeTrue()
        ->and($incoming->isPartOfTransfer())->toBeTrue()
        ->and($pair->pluck('owner_entity_id')->all())->toEqualCanonicalizing([$llc->id, $personal->id]);
});

test('an entity exposes its planned transactions', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->count(3)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
    ]);

    expect($entity->plannedTransactions()->count())->toBe(3);
});

test('deleting a counterparty cascades to its planned transactions', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->count(2)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
    ]);

    $cp->delete();

    expect(PlannedTransaction::count())->toBe(0);
});

test('deleting an entity cascades to planned transactions and its mirror counterparty', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $mirror = Counterparty::factory()->internal($entity)->create();
    $externalCp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $externalCp->id,
    ]);

    $entity->delete();

    expect(PlannedTransaction::count())->toBe(0)
        ->and(Counterparty::find($mirror->id))->toBeNull()
        ->and(Counterparty::find($externalCp->id))->not->toBeNull();
});
