<?php

use App\Actions\CreateAccount;
use App\Actions\DeleteAccount;
use App\Enums\Currency;
use App\Events\AccountCreated;
use App\Events\AccountDeleted;
use App\Models\Account;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('creating an account marked main demotes the existing main', function () {
    Event::fake([AccountCreated::class]);
    $entity = Entity::factory()->llc()->create();
    $previousMain = Account::factory()->main()->for($entity)->create(['name' => 'Old Main']);

    $newMain = app(CreateAccount::class)->execute(
        $entity,
        'New Main',
        Currency::EUR,
        amount: 50,
        isMain: true,
    );

    expect($entity->accounts()->where('is_main', true)->count())->toBe(1)
        ->and($entity->mainAccount->id)->toBe($newMain->id)
        ->and($previousMain->fresh()->is_main)->toBeFalse();
});

test('owner can add an account to their entity', function () {
    Event::fake([AccountCreated::class]);
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    Account::factory()->main()->for($entity)->create();

    $this->actingAs($user)
        ->post(route('accounts.store', $entity), [
            'name' => 'Savings',
            'currency' => 'TND',
            'amount' => '200.00',
            'is_main' => false,
        ])
        ->assertRedirect();

    expect($entity->accounts()->count())->toBe(2)
        ->and($entity->accounts()->where('name', 'Savings')->first())
        ->currency->toBe(Currency::TND)
        ->amount->toBe('200.00')
        ->is_main->toBeFalse();

    Event::assertDispatched(AccountCreated::class);
});

test('adding an account as main demotes the previous main via the controller', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $oldMain = Account::factory()->main()->for($entity)->create(['name' => 'Old']);

    $this->actingAs($user)
        ->post(route('accounts.store', $entity), [
            'name' => 'New Main',
            'currency' => 'EUR',
            'amount' => '0',
            'is_main' => true,
        ])
        ->assertRedirect();

    expect($entity->mainAccount->name)->toBe('New Main')
        ->and($oldMain->fresh()->is_main)->toBeFalse()
        ->and($entity->accounts()->where('is_main', true)->count())->toBe(1);
});

test('non-owner cannot add an account to another users entity', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $entity = Entity::factory()->llc()->for($owner)->create();
    Account::factory()->main()->for($entity)->create();

    $this->actingAs($intruder)
        ->post(route('accounts.store', $entity), [
            'name' => 'Sneaky',
            'currency' => 'TND',
            'amount' => '0',
            'is_main' => false,
        ])
        ->assertForbidden();

    expect($entity->accounts()->count())->toBe(1);
});

test('store validates account fields', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    Account::factory()->main()->for($entity)->create();

    $this->actingAs($user)
        ->post(route('accounts.store', $entity), [
            'name' => '',
            'currency' => 'XYZ',
            'amount' => 'abc',
            'is_main' => 'banana',
        ])
        ->assertSessionHasErrors(['name', 'currency', 'amount', 'is_main']);
});

test('DeleteAccount action removes the account and dispatches AccountDeleted', function () {
    Event::fake([AccountDeleted::class]);
    $entity = Entity::factory()->llc()->create();
    Account::factory()->main()->for($entity)->create();
    $secondary = Account::factory()->for($entity)->create(['is_main' => false]);

    app(DeleteAccount::class)->execute($secondary);

    expect(Account::query()->whereKey($secondary->id)->exists())->toBeFalse();
    Event::assertDispatched(AccountDeleted::class, fn (AccountDeleted $event) => $event->accountId === $secondary->id && $event->entityId === $entity->id);
});

test('owner can delete a non-main account via the controller', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    Account::factory()->main()->for($entity)->create();
    $secondary = Account::factory()->for($entity)->create(['is_main' => false]);

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $secondary))
        ->assertRedirect();

    expect(Account::query()->whereKey($secondary->id)->exists())->toBeFalse()
        ->and($entity->fresh()->accounts()->count())->toBe(1);
});

test('owner cannot delete the main account', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $main = Account::factory()->main()->for($entity)->create();

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $main))
        ->assertForbidden();

    expect(Account::query()->whereKey($main->id)->exists())->toBeTrue();
});

test('non-owner cannot delete another users account', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $entity = Entity::factory()->llc()->for($owner)->create();
    Account::factory()->main()->for($entity)->create();
    $secondary = Account::factory()->for($entity)->create(['is_main' => false]);

    $this->actingAs($intruder)
        ->delete(route('accounts.destroy', $secondary))
        ->assertForbidden();

    expect(Account::query()->whereKey($secondary->id)->exists())->toBeTrue();
});
