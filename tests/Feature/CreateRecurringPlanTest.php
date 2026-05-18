<?php

use App\Enums\CounterpartyKind;
use App\Enums\PlannedTransactionDirection;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanCreated;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function baseRecurringPlanPayload(int $entityId): array
{
    return [
        'owner_entity_id' => $entityId,
        'direction' => 'outgoing',
        'currency' => 'EUR',
        'label' => 'Apartment rent',
        'purpose' => 'Rent',
        'is_mandatory' => true,
        'starts_on' => '2026-06-01',
        'amount' => 1000,
        'frequency' => 'monthly',
        'interval_step' => 1,
        'anchor_day' => 1,
    ];
}

test('guests cannot create recurring plans', function () {
    $this->post(route('recurring-plans.store'))->assertRedirect(route('login'));
});

test('creating with a new external counterparty creates the plan, initial phase, counterparty, and event', function () {
    Event::fake([RecurringPlanCreated::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($entity->id),
            'counterparty_mode' => 'external',
            'external_name' => 'Landlord',
        ],
    )->assertRedirect(route('recurring-plans.index'));

    expect(Counterparty::count())->toBe(1)
        ->and(Counterparty::first())
        ->name->toBe('Landlord')
        ->kind->toBe(CounterpartyKind::EXTERNAL);

    expect(RecurringPlan::count())->toBe(1);
    $plan = RecurringPlan::first();
    expect($plan)
        ->owner_entity_id->toBe($entity->id)
        ->label->toBe('Apartment rent')
        ->direction->toBe(PlannedTransactionDirection::OUTGOING)
        ->status->toBe(RecurringPlanStatus::ACTIVE);

    expect(RecurringPlanPhase::count())->toBe(1);
    $phase = RecurringPlanPhase::first();
    expect($phase)
        ->recurring_plan_id->toBe($plan->id)
        ->frequency->toBe(RecurringFrequency::MONTHLY)
        ->interval_step->toBe(1)
        ->anchor_day->toBe(1)
        ->ends_on->toBeNull();

    expect((float) $phase->amount)->toBe(1000.00);

    Event::assertDispatched(RecurringPlanCreated::class);
});

test('creating with an existing external counterparty reuses it', function () {
    Event::fake([RecurringPlanCreated::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $existing = Counterparty::factory()->external()->for($user)->create(['name' => 'Tax Office']);

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($entity->id),
            'counterparty_mode' => 'external',
            'counterparty_id' => $existing->id,
        ],
    )->assertRedirect(route('recurring-plans.index'));

    expect(Counterparty::count())->toBe(1)
        ->and(RecurringPlan::first()->counterparty_id)->toBe($existing->id);
});

test('creating with internal counterparty stores the linked entity counterparty', function () {
    Event::fake([RecurringPlanCreated::class]);

    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create(['name' => 'My LLC']);
    $personal = Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($llc->id),
            'counterparty_mode' => 'internal',
            'internal_entity_id' => $personal->id,
            'amount' => 500,
            'label' => 'Owner draw',
        ],
    )->assertRedirect(route('recurring-plans.index'));

    expect(RecurringPlan::count())->toBe(1);
    $plan = RecurringPlan::first();
    expect($plan->counterparty->kind)->toBe(CounterpartyKind::INTERNAL)
        ->and($plan->counterparty->entity_id)->toBe($personal->id);
});

test('internal mode requires an internal_entity_id', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($entity->id),
            'counterparty_mode' => 'internal',
        ],
    )->assertSessionHasErrors('internal_entity_id');
});

test('external mode requires either a counterparty id or a new name', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($entity->id),
            'counterparty_mode' => 'external',
        ],
    )->assertSessionHasErrors('external_name');
});

test('a user cannot create a recurring plan on another users entity', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobsEntity = Entity::factory()->llc()->for($bob)->create();

    $this->actingAs($alice)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($bobsEntity->id),
            'counterparty_mode' => 'external',
            'external_name' => 'Landlord',
        ],
    )->assertForbidden();

    expect(RecurringPlan::count())->toBe(0);
});

test('amount must be positive', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($entity->id),
            'amount' => -5,
            'counterparty_mode' => 'external',
            'external_name' => 'Landlord',
        ],
    )->assertSessionHasErrors('amount');
});

test('account must belong to the chosen owner entity', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $otherEntity = Entity::factory()->llc()->for($user)->create();
    $foreignAccount = Account::factory()->for($otherEntity)->create();

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($entity->id),
            'counterparty_mode' => 'external',
            'external_name' => 'Landlord',
            'account_id' => $foreignAccount->id,
        ],
    )->assertNotFound();

    expect(RecurringPlan::count())->toBe(0);
});

test('account from the owner entity is recorded on the plan', function () {
    Event::fake([RecurringPlanCreated::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $account = Account::factory()->for($entity)->create(['name' => 'Operating']);

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            ...baseRecurringPlanPayload($entity->id),
            'counterparty_mode' => 'external',
            'external_name' => 'Landlord',
            'account_id' => $account->id,
        ],
    )->assertRedirect(route('recurring-plans.index'));

    expect(RecurringPlan::first()->account_id)->toBe($account->id);
});

test('the index shows the user plans only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $entity = Entity::factory()->llc()->for($user)->create();
    $foreignEntity = Entity::factory()->llc()->for($other)->create();

    RecurringPlan::factory()->for($entity, 'ownerEntity')->for(Counterparty::factory()->external()->for($user))->create(['label' => 'Mine']);
    RecurringPlan::factory()->for($foreignEntity, 'ownerEntity')->for(Counterparty::factory()->external()->for($other))->create(['label' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('recurring-plans.index'))
        ->assertInertia(fn ($page) => $page
            ->component('recurring-plans/Index')
            ->has('plans', 1)
            ->where('plans.0.label', 'Mine')
        );
});
