<?php

use App\Actions\MaterializeRecurringPlan;
use App\Actions\PauseRecurringPlan;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringPlanStatus;
use App\Events\RecurringPlanDeleted;
use App\Events\RecurringPlanEnded;
use App\Events\RecurringPlanPaused;
use App\Events\RecurringPlanResumed;
use App\Events\RecurringPlanUpdated;
use App\Models\Account;
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

function makeMaterializedPlan(User $user): RecurringPlan
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

test('pause sets status and cancels future planned rows but keeps recorded ones', function () {
    CarbonImmutable::setTestNow('2026-07-15');

    $user = User::factory()->create();
    $plan = makeMaterializedPlan($user);

    // Settle June row (in the past — should never be touched).
    $juneRow = PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-06-01')->firstOrFail();
    Transaction::factory()->for($juneRow, 'plannedTransaction')->create([
        'amount' => 1000,
        'occurred_on' => '2026-06-01',
    ]);
    $juneRow->update(['status' => PlannedTransactionStatus::SETTLED]);

    Event::fake([RecurringPlanPaused::class]);

    $this->actingAs($user)
        ->post(route('recurring-plans.pause', $plan))
        ->assertRedirect();

    expect($plan->fresh()->status)->toBe(RecurringPlanStatus::PAUSED);

    // Future planned rows (Aug, Sep — Jul might still be there since it's today=7/15 and Jul 1 < today).
    expect(PlannedTransaction::query()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '>=', '2026-07-15')
        ->count())->toBe(0);

    // Past settled row preserved.
    expect(PlannedTransaction::query()
        ->where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '2026-06-01')
        ->where('status', PlannedTransactionStatus::SETTLED->value)
        ->exists())->toBeTrue();

    Event::assertDispatched(RecurringPlanPaused::class);
});

test('resume re-materializes upcoming rows', function () {
    CarbonImmutable::setTestNow('2026-07-15');

    $user = User::factory()->create();
    $plan = makeMaterializedPlan($user);

    // Pause first
    app(PauseRecurringPlan::class)->execute($plan);
    expect(PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '>=', '2026-07-15')->count())->toBe(0);

    Event::fake([RecurringPlanResumed::class]);

    $this->actingAs($user)
        ->post(route('recurring-plans.resume', $plan))
        ->assertRedirect();

    expect($plan->fresh()->status)->toBe(RecurringPlanStatus::ACTIVE);
    expect(PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '>=', '2026-07-15')->count())->toBeGreaterThan(0);

    Event::assertDispatched(RecurringPlanResumed::class);
});

test('end sets status + ends_on and closes current phase + cancels rows past end date', function () {
    CarbonImmutable::setTestNow('2026-07-15');

    $user = User::factory()->create();
    $plan = makeMaterializedPlan($user);

    Event::fake([RecurringPlanEnded::class]);

    $this->actingAs($user)
        ->post(route('recurring-plans.end', $plan), ['ends_on' => '2026-07-31'])
        ->assertRedirect();

    $fresh = $plan->fresh(['phases']);
    expect($fresh->status)->toBe(RecurringPlanStatus::ENDED);
    expect($fresh->ends_on->toDateString())->toBe('2026-07-31');
    expect($fresh->phases->first()->ends_on->toDateString())->toBe('2026-07-31');

    expect(PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '>', '2026-07-31')->count())->toBe(0);

    Event::assertDispatched(RecurringPlanEnded::class);
});

test('delete soft-deletes the plan and cancels future rows', function () {
    CarbonImmutable::setTestNow('2026-07-15');

    $user = User::factory()->create();
    $plan = makeMaterializedPlan($user);

    Event::fake([RecurringPlanDeleted::class]);

    $this->actingAs($user)
        ->delete(route('recurring-plans.destroy', $plan), ['reason' => 'No longer needed'])
        ->assertRedirect(route('recurring-plans.index'));

    expect(RecurringPlan::find($plan->id))->toBeNull();
    expect(RecurringPlan::withTrashed()->find($plan->id))->not->toBeNull();

    expect(PlannedTransaction::where('recurring_plan_id', $plan->id)
        ->whereDate('due_date', '>=', '2026-07-15')->count())->toBe(0);

    Event::assertDispatched(RecurringPlanDeleted::class);
});

test('update changes header fields without touching phases or planned rows', function () {
    CarbonImmutable::setTestNow('2026-07-01');

    $user = User::factory()->create();
    $plan = makeMaterializedPlan($user);

    $account = Account::factory()->for($plan->ownerEntity)->create(['name' => 'Operating']);
    $plannedCount = PlannedTransaction::where('recurring_plan_id', $plan->id)->count();

    Event::fake([RecurringPlanUpdated::class]);

    $this->actingAs($user)
        ->put(route('recurring-plans.update', $plan), [
            'label' => 'Rent (updated)',
            'account_id' => $account->id,
            'purpose' => 'Rent',
            'is_mandatory' => true,
            'ends_on' => null,
            'note' => 'Lease renewed',
        ])
        ->assertRedirect();

    $fresh = $plan->fresh();
    expect($fresh->label)->toBe('Rent (updated)')
        ->and($fresh->account_id)->toBe($account->id)
        ->and($fresh->note)->toBe('Lease renewed');

    expect(PlannedTransaction::where('recurring_plan_id', $plan->id)->count())->toBe($plannedCount);

    Event::assertDispatched(RecurringPlanUpdated::class);
});

test('authorization: another user cannot modify a plan', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $plan = makeMaterializedPlan($alice);

    $this->actingAs($bob)
        ->post(route('recurring-plans.pause', $plan))
        ->assertForbidden();

    $this->actingAs($bob)
        ->delete(route('recurring-plans.destroy', $plan))
        ->assertForbidden();
});

test('show page renders for owner with phases and upcoming', function () {
    CarbonImmutable::setTestNow('2026-07-01');

    $user = User::factory()->create();
    $plan = makeMaterializedPlan($user);

    $this->actingAs($user)
        ->get(route('recurring-plans.show', $plan))
        ->assertInertia(fn ($page) => $page
            ->component('recurring-plans/Show')
            ->where('plan.id', $plan->id)
            ->has('plan.phases', 1)
            ->has('upcoming')
        );
});
