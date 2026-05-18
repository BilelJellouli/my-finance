<?php

use App\Actions\MaterializeRecurringPlan;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanMaterialized;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function makeMonthlyPlan(User $user, string $startsOn, float $amount = 1000): RecurringPlan
{
    $entity = Entity::factory()->llc()->for($user)->create();
    $counterparty = Counterparty::factory()->external()->for($user)->create(['name' => 'Landlord']);

    $plan = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($counterparty)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => $startsOn,
            'ends_on' => null,
            'materialized_until' => null,
        ]);

    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => $amount,
        'frequency' => RecurringFrequency::MONTHLY,
        'interval_step' => 1,
        'anchor_day' => null,
        'starts_on' => $startsOn,
        'ends_on' => null,
        'occurrence_count' => null,
    ]);

    return $plan->fresh(['phases', 'ownerEntity', 'counterparty']);
}

test('materializes monthly occurrences up to horizon', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMonthlyPlan($user, '2026-06-01');

    $action = app(MaterializeRecurringPlan::class);
    $created = $action->execute($plan, CarbonImmutable::parse('2026-09-30'));

    expect($created)->toBe(4); // Jun, Jul, Aug, Sep

    $rows = PlannedTransaction::query()->orderBy('due_date')->get();
    expect($rows)->toHaveCount(4);
    expect($rows[0]->due_date->toDateString())->toBe('2026-06-01');
    expect($rows[3]->due_date->toDateString())->toBe('2026-09-01');

    expect($plan->fresh()->materialized_until->toDateString())->toBe('2026-09-30');
});

test('materialization is idempotent', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMonthlyPlan($user, '2026-06-01');

    $action = app(MaterializeRecurringPlan::class);
    $first = $action->execute($plan, CarbonImmutable::parse('2026-09-30'));
    $second = $action->execute($plan->fresh(['phases']), CarbonImmutable::parse('2026-09-30'));

    expect($first)->toBe(4);
    expect($second)->toBe(0);
    expect(PlannedTransaction::count())->toBe(4);
});

test('paused and ended plans are not materialized', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMonthlyPlan($user, '2026-06-01');

    $plan->update(['status' => RecurringPlanStatus::PAUSED]);

    $action = app(MaterializeRecurringPlan::class);
    expect($action->execute($plan->fresh(['phases']), CarbonImmutable::parse('2026-09-30')))->toBe(0);
    expect(PlannedTransaction::count())->toBe(0);

    $plan->update(['status' => RecurringPlanStatus::ENDED]);
    expect($action->execute($plan->fresh(['phases']), CarbonImmutable::parse('2026-09-30')))->toBe(0);
});

test('respects plan ends_on as upper bound', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMonthlyPlan($user, '2026-06-01');
    $plan->update(['ends_on' => '2026-07-15']);

    $action = app(MaterializeRecurringPlan::class);
    $created = $action->execute($plan->fresh(['phases']), CarbonImmutable::parse('2026-12-31'));

    expect($created)->toBe(2); // Jun 1, Jul 1
});

test('respects phase ends_on as upper bound', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMonthlyPlan($user, '2026-06-01');
    $plan->phases()->update(['ends_on' => '2026-07-31']);

    $action = app(MaterializeRecurringPlan::class);
    $created = $action->execute($plan->fresh(['phases']), CarbonImmutable::parse('2026-12-31'));

    expect($created)->toBe(2);
});

test('respects phase occurrence_count', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMonthlyPlan($user, '2026-06-01');
    $plan->phases()->update(['occurrence_count' => 3]);

    $action = app(MaterializeRecurringPlan::class);
    $created = $action->execute($plan->fresh(['phases']), CarbonImmutable::parse('2026-12-31'));

    expect($created)->toBe(3);
});

test('internal counterparty plans materialize transfer pairs sharing transfer_group_id', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create();
    $personal = Entity::factory()->personal()->for($user)->create();

    $internalCp = Counterparty::factory()->internal($personal)->create();

    $plan = RecurringPlan::factory()
        ->for($llc, 'ownerEntity')
        ->for($internalCp)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => '2026-06-01',
        ]);

    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => 500,
        'frequency' => RecurringFrequency::MONTHLY,
        'interval_step' => 1,
        'starts_on' => '2026-06-01',
    ]);

    $action = app(MaterializeRecurringPlan::class);
    $action->execute($plan->fresh(['phases', 'ownerEntity', 'counterparty']), CarbonImmutable::parse('2026-07-31'));

    $rows = PlannedTransaction::orderBy('id')->get();
    expect($rows)->toHaveCount(4); // 2 occurrences × 2 sides

    $juneRows = $rows->filter(fn (PlannedTransaction $r) => $r->due_date->toDateString() === '2026-06-01');
    expect($juneRows)->toHaveCount(2);

    $primary = $juneRows->firstWhere('owner_entity_id', $llc->id);
    $mirror = $juneRows->firstWhere('owner_entity_id', $personal->id);
    expect($primary->transfer_group_id)->toBe($mirror->transfer_group_id);
    expect($primary->direction)->toBe(PlannedTransactionDirection::OUTGOING);
    expect($mirror->direction)->toBe(PlannedTransactionDirection::INCOMING);
});

test('dispatches RecurringPlanMaterialized with created count', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    Event::fake([RecurringPlanMaterialized::class]);

    $user = User::factory()->create();
    $plan = makeMonthlyPlan($user, '2026-06-01');

    app(MaterializeRecurringPlan::class)->execute($plan, CarbonImmutable::parse('2026-09-30'));

    Event::assertDispatched(
        RecurringPlanMaterialized::class,
        fn (RecurringPlanMaterialized $event) => $event->createdCount === 4 && $event->plan->id === $plan->id,
    );
});

test('creating a plan synchronously materializes upcoming occurrences', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)->post(
        route('recurring-plans.store'),
        [
            'owner_entity_id' => $entity->id,
            'counterparty_mode' => 'external',
            'external_name' => 'Landlord',
            'direction' => 'outgoing',
            'currency' => 'EUR',
            'label' => 'Rent',
            'is_mandatory' => true,
            'starts_on' => '2026-06-01',
            'amount' => 1000,
            'frequency' => 'monthly',
            'interval_step' => 1,
        ],
    )->assertRedirect(route('recurring-plans.index'));

    expect(PlannedTransaction::count())->toBeGreaterThan(0)
        ->and(PlannedTransaction::first()->status)->toBe(PlannedTransactionStatus::PLANNED)
        ->and(PlannedTransaction::first()->recurring_plan_id)->toBe(RecurringPlan::first()->id);
});

test('console command materializes only active plans', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();

    $active = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->active()
        ->create(['starts_on' => '2026-06-01']);
    RecurringPlanPhase::factory()->for($active, 'recurringPlan')->create([
        'amount' => 100,
        'frequency' => RecurringFrequency::MONTHLY,
        'starts_on' => '2026-06-01',
    ]);

    $paused = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->paused()
        ->create(['starts_on' => '2026-06-01']);
    RecurringPlanPhase::factory()->for($paused, 'recurringPlan')->create([
        'amount' => 200,
        'frequency' => RecurringFrequency::MONTHLY,
        'starts_on' => '2026-06-01',
    ]);

    $this->artisan('recurring:materialize', ['--horizon-days' => 60])->assertSuccessful();

    expect(PlannedTransaction::where('recurring_plan_id', $active->id)->count())->toBeGreaterThan(0)
        ->and(PlannedTransaction::where('recurring_plan_id', $paused->id)->count())->toBe(0);
});
