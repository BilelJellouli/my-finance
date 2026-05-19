<?php

use App\Enums\Currency;
use App\Enums\PlannedTransactionStatus;
use App\Models\Account;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated user without entities sees empty state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('entities', [])
            ->where('selected_entity_id', null)
            ->where('period.days', 60)
        );
});

test('selected entity defaults to first entity ordered with personal first', function () {
    $user = User::factory()->create();
    Entity::factory()->llc()->for($user)->create(['name' => 'Z LLC']);
    $personal = Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected_entity_id', $personal->id)
        );
});

test('dashboard aggregates incoming and outgoing per entity and currency in the window', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->personal()->for($user)->create();
    Account::factory()->main()->for($entity)->create([
        'amount' => 1000.00,
        'currency' => Currency::EUR,
    ]);
    $cp = Counterparty::factory()->external()->for($user)->create();

    PlannedTransaction::factory()->incoming()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 300,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(10)->toDateString(),
    ]);
    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 120,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(20)->toDateString(),
    ]);
    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 999,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(120)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['period_days' => 60]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entities.0.currencies.0.cash_now', '1000.00')
            ->where('entities.0.currencies.0.incoming', '300.00')
            ->where('entities.0.currencies.0.outgoing', '120.00')
            ->where('entities.0.currencies.0.end_balance', '1180.00')
            ->where('entities.0.currencies.0.is_covered', true)
        );
});

test('overdue transactions are included regardless of due date', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->personal()->for($user)->create();
    Account::factory()->main()->for($entity)->create(['amount' => 100, 'currency' => Currency::EUR]);
    $cp = Counterparty::factory()->external()->for($user)->create();

    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 500,
        'currency' => Currency::EUR,
        'due_date' => now()->subDays(15)->toDateString(),
        'status' => PlannedTransactionStatus::OVERDUE,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('entities.0.currencies.0.outgoing', '500.00')
            ->where('entities.0.currencies.0.end_balance', '-400.00')
            ->where('entities.0.currencies.0.is_covered', false)
        );
});

test('settled and cancelled transactions are excluded from projection', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->personal()->for($user)->create();
    Account::factory()->main()->for($entity)->create(['amount' => 500, 'currency' => Currency::EUR]);
    $cp = Counterparty::factory()->external()->for($user)->create();

    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 100,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => PlannedTransactionStatus::SETTLED,
    ]);
    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 200,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(5)->toDateString(),
        'status' => PlannedTransactionStatus::CANCELLED,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('entities.0.currencies.0.incoming', '0.00')
            ->where('entities.0.currencies.0.outgoing', '0.00')
            ->where('entities.0.currencies.0.end_balance', '500.00')
        );
});

test('inter-entity outgoing rows become flow edges and incoming mirrors are not duplicated', function () {
    $user = User::factory()->create();
    $personal = Entity::factory()->personal()->for($user)->create();
    $llc = Entity::factory()->llc()->for($user)->create();

    Account::factory()->main()->for($personal)->create(['amount' => 1000, 'currency' => Currency::EUR]);
    Account::factory()->main()->for($llc)->create(['amount' => 2000, 'currency' => Currency::EUR]);

    $personalMirror = Counterparty::factory()->internal($personal)->create();
    $llcMirror = Counterparty::factory()->internal($llc)->create();

    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $personal->id,
        'counterparty_id' => $llcMirror->id,
        'amount' => 500,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(10)->toDateString(),
    ]);
    PlannedTransaction::factory()->incoming()->create([
        'owner_entity_id' => $llc->id,
        'counterparty_id' => $personalMirror->id,
        'amount' => 500,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(10)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('flows.0.from_entity_id', $personal->id)
            ->where('flows.0.to_entity_id', $llc->id)
            ->where('flows.0.amount', '500.00')
            ->where('flows.0.count', 1)
            ->has('flows', 1)
        );
});

test('timeline is built for the selected entity with running balance', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->personal()->for($user)->create();
    Account::factory()->main()->for($entity)->create(['amount' => 1000, 'currency' => Currency::EUR]);
    $cp = Counterparty::factory()->external()->for($user)->create();

    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 200,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(5)->toDateString(),
        'purpose' => 'Rent',
    ]);
    PlannedTransaction::factory()->incoming()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 800,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(10)->toDateString(),
        'purpose' => 'Salary',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('timeline.0.label', 'Rent')
            ->where('timeline.0.running_balance', '800.00')
            ->where('timeline.1.label', 'Salary')
            ->where('timeline.1.running_balance', '1600.00')
        );
});

test('user only sees their own entities', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Entity::factory()->personal()->for($bob)->create();
    $aliceEntity = Entity::factory()->personal()->for($alice)->create();
    Account::factory()->main()->for($aliceEntity)->create(['amount' => 100, 'currency' => Currency::EUR]);

    $this->actingAs($alice)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('entities', 1)
            ->where('entities.0.id', $aliceEntity->id)
        );
});

test('invalid period_days falls back to default 60', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['period_days' => 9999]))
        ->assertInertia(fn ($page) => $page->where('period.days', 60));
});

test('explicit entity_id selects that entity when owned by user', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();
    $llc = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['entity_id' => $llc->id]))
        ->assertInertia(fn ($page) => $page->where('selected_entity_id', $llc->id));
});

test('undated planned transactions for the selected entity are returned separately', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->personal()->for($user)->create();
    Account::factory()->main()->for($entity)->create(['amount' => 1000, 'currency' => Currency::EUR]);
    $cp = Counterparty::factory()->external()->for($user)->create();

    // Dated row — should not appear in undated payload
    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 50,
        'currency' => Currency::EUR,
        'due_date' => now()->addDays(5)->toDateString(),
    ]);
    // Undated debt obligation
    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 700,
        'currency' => Currency::EUR,
        'due_date' => null,
        'purpose' => 'Loan repayment',
    ]);
    // Undated incoming
    PlannedTransaction::factory()->incoming()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 250,
        'currency' => Currency::EUR,
        'due_date' => null,
        'purpose' => 'Pending invoice',
    ]);
    // Settled undated — should be excluded
    PlannedTransaction::factory()->outgoing()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'amount' => 999,
        'currency' => Currency::EUR,
        'due_date' => null,
        'status' => PlannedTransactionStatus::SETTLED,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('undated.items', 2)
            ->has('undated.totals', 1)
            ->where('undated.totals.0.currency', 'EUR')
            ->where('undated.totals.0.incoming', '250.00')
            ->where('undated.totals.0.outgoing', '700.00')
            ->where('undated.totals.0.count', 2)
            // Dated row's projection still works
            ->where('entities.0.currencies.0.outgoing', '50.00')
        );
});

test('undated payload is empty when entity has no undated planned rows', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('undated.items', [])
            ->where('undated.totals', [])
        );
});

test('entity_id belonging to another user is ignored', function () {
    $user = User::factory()->create();
    $personal = Entity::factory()->personal()->for($user)->create();
    $otherUserEntity = Entity::factory()->personal()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['entity_id' => $otherUserEntity->id]))
        ->assertInertia(fn ($page) => $page->where('selected_entity_id', $personal->id));
});
