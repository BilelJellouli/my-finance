<?php

use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access the planned transactions index', function () {
    $this->get(route('planned-transactions.index'))->assertRedirect(route('login'));
});

test('user sees only their own planned transactions', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $aliceEntity = Entity::factory()->llc()->for($alice)->create();
    $bobEntity = Entity::factory()->llc()->for($bob)->create();

    $aliceCp = Counterparty::factory()->for($alice)->create();
    $bobCp = Counterparty::factory()->for($bob)->create();

    PlannedTransaction::factory()->create([
        'owner_entity_id' => $aliceEntity->id,
        'counterparty_id' => $aliceCp->id,
        'purpose' => 'Alice rent',
    ]);
    PlannedTransaction::factory()->create([
        'owner_entity_id' => $bobEntity->id,
        'counterparty_id' => $bobCp->id,
        'purpose' => 'Bob rent',
    ]);

    $response = $this->actingAs($alice)->get(route('planned-transactions.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('planned-transactions/Index')
            ->where('transactions.meta.total', 1)
            ->where('transactions.data.0.purpose', 'Alice rent')
        );
});

test('filtering by direction returns only matching rows', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->incoming()->count(2)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
    ]);
    PlannedTransaction::factory()->outgoing()->count(3)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
    ]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['direction' => 'outgoing']))
        ->assertInertia(fn ($page) => $page->where('transactions.meta.total', 3));
});

test('filtering by entity returns only that entity', function () {
    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create();
    $personal = Entity::factory()->personal()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->count(2)->create([
        'owner_entity_id' => $llc->id,
        'counterparty_id' => $cp->id,
    ]);
    PlannedTransaction::factory()->count(4)->create([
        'owner_entity_id' => $personal->id,
        'counterparty_id' => $cp->id,
    ]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['entity_id' => $personal->id]))
        ->assertInertia(fn ($page) => $page->where('transactions.meta.total', 4));
});

test('filtering by mandatory yes excludes flexible rows', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->count(2)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'is_mandatory' => true,
    ]);
    PlannedTransaction::factory()->flexible()->count(3)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
    ]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['mandatory' => 'yes']))
        ->assertInertia(fn ($page) => $page->where('transactions.meta.total', 2));

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['mandatory' => 'no']))
        ->assertInertia(fn ($page) => $page->where('transactions.meta.total', 3));
});

test('filtering by purpose returns only matching rows', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->count(2)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'purpose' => 'Rent',
    ]);
    PlannedTransaction::factory()->count(1)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'purpose' => 'Salary',
    ]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['purpose' => 'Rent']))
        ->assertInertia(fn ($page) => $page->where('transactions.meta.total', 2));
});

test('filtering by due_date range works', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'due_date' => '2026-04-01',
    ]);
    PlannedTransaction::factory()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'due_date' => '2026-06-15',
    ]);
    PlannedTransaction::factory()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'due_date' => '2026-09-01',
    ]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['due_from' => '2026-05-01', 'due_to' => '2026-08-01']))
        ->assertInertia(fn ($page) => $page->where('transactions.meta.total', 1));
});

test('sorting by due_date ascending orders correctly', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'due_date' => '2026-09-01',
        'purpose' => 'Late',
    ]);
    PlannedTransaction::factory()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'due_date' => '2026-05-01',
        'purpose' => 'Early',
    ]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['sort' => 'due_date', 'dir' => 'asc']))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.data.0.purpose', 'Early')
            ->where('transactions.data.1.purpose', 'Late')
        );
});

test('invalid sort column is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('planned-transactions.index', ['sort' => 'note']))
        ->assertSessionHasErrors('sort');
});

test('purpose options expose distinct user values', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $cp = Counterparty::factory()->for($user)->create();

    PlannedTransaction::factory()->count(3)->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'purpose' => 'Rent',
    ]);
    PlannedTransaction::factory()->create([
        'owner_entity_id' => $entity->id,
        'counterparty_id' => $cp->id,
        'purpose' => 'Salary',
    ]);

    $this->actingAs($user)
        ->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page->where('options.purposes', ['Rent', 'Salary']));
});
