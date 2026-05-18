<?php

use App\Actions\MaterializeRecurringPlan;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringFrequency;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMaterializedExternalPlan(User $user): RecurringPlan
{
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();

    $plan = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => '2026-06-01',
        ]);
    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => 1000,
        'frequency' => RecurringFrequency::MONTHLY,
        'starts_on' => '2026-06-01',
    ]);

    app(MaterializeRecurringPlan::class)
        ->execute($plan->fresh(['phases', 'ownerEntity', 'counterparty']), CarbonImmutable::parse('2026-09-30'));

    return $plan->fresh(['phases', 'ownerEntity', 'counterparty']);
}

test('default index view collapses recurring rows to only the next planned occurrence', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMaterializedExternalPlan($user); // 4 rows materialized: Jun, Jul, Aug, Sep

    expect(PlannedTransaction::where('recurring_plan_id', $plan->id)->count())->toBe(4);

    $this->actingAs($user)
        ->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.meta.total', 1)
            ->where('transactions.data.0.due_date', '2026-06-01')
            ->where('filters.recurring_view', 'next')
        );
});

test('passing recurring_view=all shows every materialized row', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    makeMaterializedExternalPlan($user);

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['recurring_view' => 'all']))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.meta.total', 4)
            ->where('filters.recurring_view', 'all')
        );
});

test('once the next row is settled, the next collapse falls to the following month', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMaterializedExternalPlan($user);

    PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-06-01')
        ->update(['status' => PlannedTransactionStatus::SETTLED]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.meta.total', 1)
            ->where('transactions.data.0.due_date', '2026-07-01')
        );
});

test('one-off (non-recurring) planned transactions are unaffected by the collapse', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makeMaterializedExternalPlan($user);

    $entity = $plan->ownerEntity;
    $cp = $plan->counterparty;

    // Two one-off planned transactions.
    PlannedTransaction::factory()->for($entity, 'ownerEntity')->for($cp)->create([
        'amount' => 50,
        'due_date' => '2026-06-15',
        'recurring_plan_id' => null,
    ]);
    PlannedTransaction::factory()->for($entity, 'ownerEntity')->for($cp)->create([
        'amount' => 75,
        'due_date' => '2026-07-15',
        'recurring_plan_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page
            // 1 from recurring (the next) + 2 one-offs = 3
            ->where('transactions.meta.total', 3)
        );
});

test('internal transfer plan keeps both legs of the next occurrence visible', function () {
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
        'starts_on' => '2026-06-01',
    ]);

    app(MaterializeRecurringPlan::class)
        ->execute($plan->fresh(['phases', 'ownerEntity', 'counterparty']), CarbonImmutable::parse('2026-09-30'));

    // 4 occurrences × 2 sides = 8 rows
    expect(PlannedTransaction::where('recurring_plan_id', $plan->id)->count())->toBe(8);

    $this->actingAs($user)
        ->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page
            // Both legs of the next (Jun 1) occurrence — one per entity.
            ->where('transactions.meta.total', 2)
        );
});
