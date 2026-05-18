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

function makePlanWithMaterializedRows(User $user): RecurringPlan
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

test('show page exposes upcoming rows with the fields the edit dialog needs', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanWithMaterializedRows($user);

    $this->actingAs($user)
        ->get(route('recurring-plans.show', $plan))
        ->assertInertia(fn ($page) => $page
            ->has('upcoming.0.id')
            ->has('upcoming.0.amount')
            ->has('upcoming.0.currency')
            ->has('upcoming.0.due_date')
            ->has('upcoming.0.status')
            ->has('upcoming.0.is_mandatory')
            ->where('upcoming.0.amount', '1000.00')
        );
});

test('updating an individual occurrence amount via the planned-transactions endpoint only affects that row', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanWithMaterializedRows($user);

    $augRow = PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-08-01')
        ->firstOrFail();

    $this->actingAs($user)
        ->put(route('planned-transactions.update', $augRow), [
            'amount' => 850.50,
            'currency' => $augRow->currency->value,
            'due_date' => $augRow->due_date->toDateString(),
            'purpose' => $augRow->purpose,
            'status' => $augRow->status->value,
            'is_mandatory' => $augRow->is_mandatory,
            'note' => 'Extra payment toward principal',
        ])
        ->assertRedirect();

    expect((float) $augRow->fresh()->amount)->toBe(850.50)
        ->and($augRow->fresh()->note)->toBe('Extra payment toward principal');

    // Other rows still at plan default.
    $sepRow = PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-09-01')->first();
    expect((float) $sepRow->amount)->toBe(1000.00);

    // No new phase was added.
    expect($plan->phases()->count())->toBe(1);
});

test('marking an occurrence cancelled via the same endpoint works', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanWithMaterializedRows($user);

    $augRow = PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-08-01')
        ->firstOrFail();

    $this->actingAs($user)
        ->put(route('planned-transactions.update', $augRow), [
            'amount' => $augRow->amount,
            'currency' => $augRow->currency->value,
            'due_date' => $augRow->due_date->toDateString(),
            'purpose' => $augRow->purpose,
            'status' => PlannedTransactionStatus::CANCELLED->value,
            'is_mandatory' => $augRow->is_mandatory,
            'note' => null,
        ])
        ->assertRedirect();

    expect($augRow->fresh()->status)->toBe(PlannedTransactionStatus::CANCELLED);
});
