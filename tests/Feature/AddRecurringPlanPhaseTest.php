<?php

use App\Actions\AddRecurringPlanPhase;
use App\Actions\MaterializeRecurringPlan;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringFrequency;
use App\Events\RecurringPlanPhaseAdded;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function makePlanForUser(User $user, string $startsOn = '2026-06-01', float $amount = 1000): RecurringPlan
{
    $entity = Entity::factory()->llc()->for($user)->create();
    $counterparty = Counterparty::factory()->external()->for($user)->create();

    $plan = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($counterparty)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => $startsOn,
        ]);

    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => $amount,
        'frequency' => RecurringFrequency::MONTHLY,
        'interval_step' => 1,
        'starts_on' => $startsOn,
    ]);

    return $plan->fresh(['phases', 'ownerEntity', 'counterparty']);
}

test('closes the current phase at cut date and opens a new phase from effective_from', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanForUser($user, '2026-06-01', 1000);

    // Materialize first so there's data to regenerate.
    app(MaterializeRecurringPlan::class)
        ->execute($plan, CarbonImmutable::parse('2026-09-30'));

    Event::fake([RecurringPlanPhaseAdded::class]);

    app(AddRecurringPlanPhase::class)->execute(
        plan: $plan->fresh(['phases', 'ownerEntity', 'counterparty']),
        amount: 1100,
        frequency: RecurringFrequency::MONTHLY,
        intervalStep: 1,
        anchorDay: null,
        effectiveFrom: '2026-08-01',
        reason: 'Rent increase',
    );

    $phases = RecurringPlanPhase::orderBy('starts_on')->get();
    expect($phases)->toHaveCount(2);

    $closed = $phases[0];
    $opened = $phases[1];

    expect($closed->ends_on->toDateString())->toBe('2026-07-31');
    expect((float) $closed->amount)->toBe(1000.00);
    expect($opened->starts_on->toDateString())->toBe('2026-08-01');
    expect($opened->ends_on)->toBeNull();
    expect((float) $opened->amount)->toBe(1100.00);
    expect($opened->reason)->toBe('Rent increase');

    Event::assertDispatched(RecurringPlanPhaseAdded::class);
});

test('cancels only future planned rows and keeps past + recorded rows', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanForUser($user, '2026-06-01', 1000);

    app(MaterializeRecurringPlan::class)
        ->execute($plan, CarbonImmutable::parse('2026-09-30'));

    // Record an actual transaction against the July planned row.
    $julyRow = PlannedTransaction::query()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-07-01')
        ->firstOrFail();

    Transaction::factory()->for($julyRow, 'plannedTransaction')->create([
        'amount' => 1000,
        'occurred_on' => '2026-07-01',
    ]);
    $julyRow->update(['status' => PlannedTransactionStatus::SETTLED]);

    app(AddRecurringPlanPhase::class)->execute(
        plan: $plan->fresh(['phases', 'ownerEntity', 'counterparty']),
        amount: 1100,
        frequency: RecurringFrequency::MONTHLY,
        intervalStep: 1,
        anchorDay: null,
        effectiveFrom: '2026-08-01',
        reason: null,
    );

    // June planned row (planned status, past) — left alone in v1 (we cancel future only).
    expect(PlannedTransaction::query()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-06-01')
        ->exists())->toBeTrue();

    // July planned row (settled) — preserved.
    expect(PlannedTransaction::query()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-07-01')
        ->where('status', PlannedTransactionStatus::SETTLED->value)
        ->exists())->toBeTrue();

    // August / September old planned rows — gone (force deleted).
    expect(PlannedTransaction::withTrashed()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-08-01')
        ->where('amount', 1000)
        ->exists())->toBeFalse();

    // August / September new planned rows at 1100 — materialized.
    $augNew = PlannedTransaction::query()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-08-01')
        ->first();
    expect($augNew)->not->toBeNull();
    expect((float) $augNew->amount)->toBe(1100.00);
});

test('cancels both legs of a transfer-pair when phase changes', function () {
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

    app(MaterializeRecurringPlan::class)
        ->execute($plan->fresh(['phases', 'ownerEntity', 'counterparty']), CarbonImmutable::parse('2026-09-30'));

    expect(PlannedTransaction::count())->toBe(4 * 2);

    app(AddRecurringPlanPhase::class)->execute(
        plan: $plan->fresh(['phases', 'ownerEntity', 'counterparty']),
        amount: 600,
        frequency: RecurringFrequency::MONTHLY,
        intervalStep: 1,
        anchorDay: null,
        effectiveFrom: '2026-08-01',
        reason: null,
    );

    // Old August + September rows (both legs each) should be gone.
    expect(PlannedTransaction::withTrashed()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '>=', '2026-08-01')
        ->where('amount', 500)
        ->count())->toBe(0);

    // New rows at 600 should exist for Aug + Sep × both legs.
    $newRows = PlannedTransaction::query()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '>=', '2026-08-01')
        ->get();
    expect($newRows)->toHaveCount(4);
    foreach ($newRows as $row) {
        expect((float) $row->amount)->toBe(600.00);
    }
});

test('effective_from must be after current phase starts_on', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $plan = makePlanForUser($user, '2026-06-01');

    expect(fn () => app(AddRecurringPlanPhase::class)->execute(
        plan: $plan,
        amount: 1100,
        frequency: RecurringFrequency::MONTHLY,
        intervalStep: 1,
        anchorDay: null,
        effectiveFrom: '2026-06-01',
        reason: null,
    ))->toThrow(InvalidArgumentException::class);
});
