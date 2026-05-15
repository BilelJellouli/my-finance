<?php

use App\Enums\CounterpartyKind;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Events\PlannedTransactionCreated;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function basePlannedTransactionPayload(int $entityId): array
{
    return [
        'owner_entity_id' => $entityId,
        'direction' => 'outgoing',
        'amount' => 1200.50,
        'currency' => 'EUR',
        'due_date' => '2026-06-01',
        'purpose' => 'Rent',
        'status' => 'planned',
        'is_mandatory' => true,
        'note' => null,
    ];
}

test('guests cannot create planned transactions', function () {
    $this->post(route('planned-transactions.store'))->assertRedirect(route('login'));
});

test('creating with a new external counterparty creates that counterparty and the transaction', function () {
    Event::fake([PlannedTransactionCreated::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $response = $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...basePlannedTransactionPayload($entity->id),
            'counterparty_mode' => 'external_new',
            'external_name' => 'Landlord',
        ],
    );

    $response->assertRedirect(route('planned-transactions.index'));

    expect(Counterparty::count())->toBe(1)
        ->and(Counterparty::first())
        ->name->toBe('Landlord')
        ->kind->toBe(CounterpartyKind::EXTERNAL);

    expect(PlannedTransaction::count())->toBe(1)
        ->and(PlannedTransaction::first())
        ->owner_entity_id->toBe($entity->id)
        ->purpose->toBe('Rent')
        ->transfer_group_id->toBeNull();

    Event::assertDispatched(PlannedTransactionCreated::class);
});

test('creating with an existing external counterparty reuses it', function () {
    Event::fake([PlannedTransactionCreated::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $existing = Counterparty::factory()->external()->for($user)->create(['name' => 'Tax Office']);

    $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...basePlannedTransactionPayload($entity->id),
            'counterparty_mode' => 'external_existing',
            'counterparty_id' => $existing->id,
        ],
    )->assertRedirect(route('planned-transactions.index'));

    expect(Counterparty::count())->toBe(1)
        ->and(PlannedTransaction::first()->counterparty_id)->toBe($existing->id);
});

test('creating with internal counterparty creates linked pair with flipped direction', function () {
    Event::fake([PlannedTransactionCreated::class]);

    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create(['name' => 'My LLC']);
    $personal = Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...basePlannedTransactionPayload($llc->id),
            'counterparty_mode' => 'internal',
            'internal_entity_id' => $personal->id,
            'amount' => 500,
            'purpose' => 'Owner draw',
        ],
    )->assertRedirect(route('planned-transactions.index'));

    $rows = PlannedTransaction::orderBy('id')->get();
    expect($rows)->toHaveCount(2);

    $first = $rows[0];
    $second = $rows[1];

    expect($first->transfer_group_id)->not->toBeNull()
        ->and($second->transfer_group_id)->toBe($first->transfer_group_id);

    expect($first->owner_entity_id)->toBe($llc->id)
        ->and($first->direction)->toBe(PlannedTransactionDirection::OUTGOING);

    expect($second->owner_entity_id)->toBe($personal->id)
        ->and($second->direction)->toBe(PlannedTransactionDirection::INCOMING);

    expect(Counterparty::where('entity_id', $llc->id)->where('kind', CounterpartyKind::INTERNAL)->exists())->toBeTrue()
        ->and(Counterparty::where('entity_id', $personal->id)->where('kind', CounterpartyKind::INTERNAL)->exists())->toBeTrue();

    Event::assertDispatched(
        PlannedTransactionCreated::class,
        fn (PlannedTransactionCreated $event) => count($event->rows) === 2,
    );
});

test('internal mode requires an internal_entity_id', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...basePlannedTransactionPayload($entity->id),
            'counterparty_mode' => 'internal',
        ],
    )->assertSessionHasErrors('internal_entity_id');
});

test('internal counterparty cannot be the owner entity itself', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...basePlannedTransactionPayload($entity->id),
            'counterparty_mode' => 'internal',
            'internal_entity_id' => $entity->id,
        ],
    )->assertSessionHasErrors('internal_entity_id');
});

test('external_new mode requires a name', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...basePlannedTransactionPayload($entity->id),
            'counterparty_mode' => 'external_new',
        ],
    )->assertSessionHasErrors('external_name');
});

test('a user cannot create a planned transaction on another users entity', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobsEntity = Entity::factory()->llc()->for($bob)->create();

    $this->actingAs($alice)->post(
        route('planned-transactions.store'),
        [
            ...basePlannedTransactionPayload($bobsEntity->id),
            'counterparty_mode' => 'external_new',
            'external_name' => 'Landlord',
        ],
    )->assertForbidden();

    expect(PlannedTransaction::count())->toBe(0);
});

test('amount must be positive', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...basePlannedTransactionPayload($entity->id),
            'amount' => -5,
            'counterparty_mode' => 'external_new',
            'external_name' => 'Landlord',
        ],
    )->assertSessionHasErrors('amount');
});

test('a planned transaction can be created without a due_date', function () {
    Event::fake([PlannedTransactionCreated::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $payload = basePlannedTransactionPayload($entity->id);
    unset($payload['due_date']);

    $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...$payload,
            'counterparty_mode' => 'external_new',
            'external_name' => 'Landlord',
        ],
    )->assertRedirect(route('planned-transactions.index'));

    expect(PlannedTransaction::first()->due_date)->toBeNull();

    $this->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.meta.total', 1)
            ->where('transactions.data.0.due_date', null)
        );
});

test('default status is planned when omitted', function () {
    Event::fake([PlannedTransactionCreated::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $payload = basePlannedTransactionPayload($entity->id);
    unset($payload['status']);

    $this->actingAs($user)->post(
        route('planned-transactions.store'),
        [
            ...$payload,
            'counterparty_mode' => 'external_new',
            'external_name' => 'Landlord',
        ],
    )->assertRedirect(route('planned-transactions.index'));

    expect(PlannedTransaction::first()->status)->toBe(PlannedTransactionStatus::PLANNED);
});
