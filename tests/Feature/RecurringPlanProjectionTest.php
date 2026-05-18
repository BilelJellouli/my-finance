<?php

use App\Enums\PlannedTransactionDirection;
use App\Enums\RecurringFrequency;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPhase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('show page returns null projection for open-ended plans', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();

    $plan = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => '2026-06-01',
            'ends_on' => null,
        ]);
    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => 1000,
        'frequency' => RecurringFrequency::MONTHLY,
        'starts_on' => '2026-06-01',
    ]);

    $this->actingAs($user)
        ->get(route('recurring-plans.show', $plan))
        ->assertInertia(fn ($page) => $page
            ->where('projection', null)
        );
});

test('show page projects total for a 12-month rent at flat amount', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();

    $plan = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-05-31',
        ]);
    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => 1000,
        'frequency' => RecurringFrequency::MONTHLY,
        'starts_on' => '2026-06-01',
    ]);

    $this->actingAs($user)
        ->get(route('recurring-plans.show', $plan))
        ->assertInertia(fn ($page) => $page
            ->where('projection.occurrences', 12)
            ->where('projection.total', '12000.00')
            ->where('projection.starts_on', '2026-06-01')
            ->where('projection.ends_on', '2027-05-31')
        );
});

test('projection sums across multiple phases', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();

    $plan = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-05-31',
        ]);

    // Phase 1: 6 months at 1000
    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => 1000,
        'frequency' => RecurringFrequency::MONTHLY,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-11-30',
    ]);

    // Phase 2: 6 months at 1100
    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => 1100,
        'frequency' => RecurringFrequency::MONTHLY,
        'starts_on' => '2026-12-01',
        'ends_on' => null,
    ]);

    $this->actingAs($user)
        ->get(route('recurring-plans.show', $plan))
        ->assertInertia(fn ($page) => $page
            ->where('projection.occurrences', 12)
            ->where('projection.total', '12600.00') // 6 × 1000 + 6 × 1100
        );
});

test('projection respects occurrence_count cap on a phase', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();

    $plan = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-05-31',
        ]);
    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => 1000,
        'frequency' => RecurringFrequency::MONTHLY,
        'starts_on' => '2026-06-01',
        'occurrence_count' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('recurring-plans.show', $plan))
        ->assertInertia(fn ($page) => $page
            ->where('projection.occurrences', 3)
            ->where('projection.total', '3000.00')
        );
});

test('projection handles weekly frequency', function () {
    CarbonImmutable::setTestNow('2026-06-01');

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->external()->for($user)->create();

    $plan = RecurringPlan::factory()
        ->for($entity, 'ownerEntity')
        ->for($cp)
        ->create([
            'direction' => PlannedTransactionDirection::OUTGOING,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-29',
        ]);
    RecurringPlanPhase::factory()->for($plan, 'recurringPlan')->create([
        'amount' => 50,
        'frequency' => RecurringFrequency::WEEKLY,
        'starts_on' => '2026-06-01',
    ]);

    $this->actingAs($user)
        ->get(route('recurring-plans.show', $plan))
        ->assertInertia(fn ($page) => $page
            ->where('projection.occurrences', 5) // Jun 1, 8, 15, 22, 29
            ->where('projection.total', '250.00')
        );
});
