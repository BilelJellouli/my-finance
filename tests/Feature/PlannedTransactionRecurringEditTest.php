<?php

use App\Actions\MaterializeRecurringPlan;
use App\Enums\PlannedTransactionDirection;
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

function makePlanWithRows(User $user): RecurringPlan
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
        'interval_step' => 1,
        'starts_on' => '2026-06-01',
    ]);

    app(MaterializeRecurringPlan::class)
        ->execute($plan->fresh(['phases', 'ownerEntity', 'counterparty']), CarbonImmutable::parse('2026-09-30'));

    return $plan->fresh(['phases', 'ownerEntity', 'counterparty']);
}

test('the planned-transactions index includes recurring_plan ref on generated rows', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanWithRows($user);

    $this->actingAs($user)
        ->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->component('planned-transactions/Index')
            ->where('transactions.data.0.recurring_plan.id', $plan->id)
            ->where('transactions.data.0.recurring_plan.label', $plan->label)
        );
});

test('one-off planned transactions have null recurring_plan', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();

    PlannedTransaction::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create();

    $this->actingAs($user)
        ->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.data.0.recurring_plan', null)
        );
});

test('hitting addPhase with effective_from = a recurring-sourced row produces a new phase', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanWithRows($user);

    $augRow = PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-08-01')
        ->firstOrFail();

    $this->actingAs($user)
        ->post(route('recurring-plans.phases.store', $plan), [
            'amount' => 1200,
            'frequency' => 'monthly',
            'interval_step' => 1,
            'anchor_day' => null,
            'effective_from' => $augRow->due_date->toDateString(),
            'reason' => 'Adjusted from row edit',
        ])
        ->assertRedirect();

    expect($plan->phases()->count())->toBe(2);
    $opened = $plan->phases()->whereNull('ends_on')->first();
    expect($opened->starts_on->toDateString())->toBe('2026-08-01')
        ->and((float) $opened->amount)->toBe(1200.00)
        ->and($opened->reason)->toBe('Adjusted from row edit');

    // Aug + Sep planned rows now have the new amount.
    $newAug = PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-08-01')->first();
    expect((float) $newAug->amount)->toBe(1200.00);
});

test('updating just one planned row still uses the existing update endpoint', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanWithRows($user);

    $augRow = PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-08-01')
        ->firstOrFail();

    $this->actingAs($user)
        ->put(route('planned-transactions.update', $augRow), [
            'amount' => 999,
            'currency' => $augRow->currency->value,
            'due_date' => $augRow->due_date->toDateString(),
            'purpose' => $augRow->purpose,
            'status' => $augRow->status->value,
            'is_mandatory' => $augRow->is_mandatory,
            'note' => 'One-off adjustment',
        ])
        ->assertRedirect();

    expect($plan->phases()->count())->toBe(1);
    expect((float) $augRow->fresh()->amount)->toBe(999.00);

    // Other future rows unchanged.
    $sepRow = PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-09-01')->first();
    expect((float) $sepRow->amount)->toBe(1000.00);
});
