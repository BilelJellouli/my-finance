<?php

use App\Actions\DeletePlannedTransaction;
use App\Events\PlannedTransactionDeleted;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('guests cannot delete planned transactions', function () {
    $txn = PlannedTransaction::factory()->create();

    $this->delete(route('planned-transactions.destroy', $txn), [
        'deletion_reason' => 'Some reason',
    ])->assertRedirect(route('login'));
});

test('owner can soft-delete a single planned transaction with a reason', function () {
    Event::fake([PlannedTransactionDeleted::class]);

    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $txn = PlannedTransaction::factory()->for($entity, 'ownerEntity')->create();

    $this->actingAs($user)
        ->delete(route('planned-transactions.destroy', $txn), [
            'deletion_reason' => 'Cancelled by client',
        ])
        ->assertRedirect();

    $fresh = PlannedTransaction::withTrashed()->find($txn->id);

    expect($fresh)
        ->deleted_at->not->toBeNull()
        ->deletion_reason->toBe('Cancelled by client');

    expect(PlannedTransaction::find($txn->id))->toBeNull();

    Event::assertDispatched(
        PlannedTransactionDeleted::class,
        fn (PlannedTransactionDeleted $event) => count($event->rows) === 1
            && $event->reason === 'Cancelled by client',
    );
});

test('deleting one row of a transfer pair soft-deletes both atomically with the same reason', function () {
    Event::fake([PlannedTransactionDeleted::class]);

    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create();
    $personal = Entity::factory()->personal()->for($user)->create();
    $llcMirror = Counterparty::factory()->internal($llc)->create();
    $personalMirror = Counterparty::factory()->internal($personal)->create();
    $group = (string) Str::uuid();

    $primary = PlannedTransaction::factory()
        ->for($llc, 'ownerEntity')
        ->for($personalMirror)
        ->outgoing()
        ->create(['transfer_group_id' => $group]);

    $mirror = PlannedTransaction::factory()
        ->for($personal, 'ownerEntity')
        ->for($llcMirror)
        ->incoming()
        ->create(['transfer_group_id' => $group]);

    $this->actingAs($user)
        ->delete(route('planned-transactions.destroy', $primary), [
            'deletion_reason' => 'Owner draw withdrawn',
        ])
        ->assertRedirect();

    $primaryFresh = PlannedTransaction::withTrashed()->find($primary->id);
    $mirrorFresh = PlannedTransaction::withTrashed()->find($mirror->id);

    expect($primaryFresh->deleted_at)->not->toBeNull()
        ->and($primaryFresh->deletion_reason)->toBe('Owner draw withdrawn');
    expect($mirrorFresh->deleted_at)->not->toBeNull()
        ->and($mirrorFresh->deletion_reason)->toBe('Owner draw withdrawn');

    expect(PlannedTransaction::count())->toBe(0);

    Event::assertDispatched(
        PlannedTransactionDeleted::class,
        fn (PlannedTransactionDeleted $event) => count($event->rows) === 2
            && $event->reason === 'Owner draw withdrawn',
    );
});

test('a user cannot delete another users planned transaction', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $entity = Entity::factory()->llc()->for($owner)->create();
    $txn = PlannedTransaction::factory()->for($entity, 'ownerEntity')->create();

    $this->actingAs($intruder)
        ->delete(route('planned-transactions.destroy', $txn), [
            'deletion_reason' => 'Trying to delete',
        ])
        ->assertForbidden();

    expect(PlannedTransaction::find($txn->id))->not->toBeNull();
});

test('deletion requires a reason', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $txn = PlannedTransaction::factory()->for($entity, 'ownerEntity')->create();

    $this->actingAs($user)
        ->delete(route('planned-transactions.destroy', $txn), [
            'deletion_reason' => '',
        ])
        ->assertSessionHasErrors('deletion_reason');

    expect(PlannedTransaction::find($txn->id))->not->toBeNull();
});

test('deletion reason must be at least three characters', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $txn = PlannedTransaction::factory()->for($entity, 'ownerEntity')->create();

    $this->actingAs($user)
        ->delete(route('planned-transactions.destroy', $txn), [
            'deletion_reason' => 'no',
        ])
        ->assertSessionHasErrors('deletion_reason');
});

test('soft-deleted rows are excluded from the index listing', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $kept = PlannedTransaction::factory()->for($entity, 'ownerEntity')->create(['purpose' => 'Visible']);
    $removed = PlannedTransaction::factory()->for($entity, 'ownerEntity')->create(['purpose' => 'Hidden']);

    $this->actingAs($user)
        ->delete(route('planned-transactions.destroy', $removed), [
            'deletion_reason' => 'Out of date',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('planned-transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.meta.total', 1)
            ->where('transactions.data.0.id', $kept->id)
        );
});

test('DeletePlannedTransaction action soft-deletes both rows of a pair', function () {
    Event::fake([PlannedTransactionDeleted::class]);

    $user = User::factory()->create();
    $a = Entity::factory()->llc()->for($user)->create();
    $b = Entity::factory()->personal()->for($user)->create();
    $aMirror = Counterparty::factory()->internal($a)->create();
    $bMirror = Counterparty::factory()->internal($b)->create();
    $group = (string) Str::uuid();

    $primary = PlannedTransaction::factory()->for($a, 'ownerEntity')->for($bMirror)->outgoing()->create([
        'transfer_group_id' => $group,
    ]);
    $mirror = PlannedTransaction::factory()->for($b, 'ownerEntity')->for($aMirror)->incoming()->create([
        'transfer_group_id' => $group,
    ]);

    $rows = app(DeletePlannedTransaction::class)->execute($primary, 'Action-level test');

    expect($rows)->toHaveCount(2);
    expect(PlannedTransaction::withTrashed()->find($mirror->id))
        ->deleted_at->not->toBeNull()
        ->deletion_reason->toBe('Action-level test');

    Event::assertDispatched(PlannedTransactionDeleted::class);
});
