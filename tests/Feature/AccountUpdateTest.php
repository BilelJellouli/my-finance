<?php

use App\Actions\UpdateAccount;
use App\Enums\Currency;
use App\Events\AccountUpdated;
use App\Models\Account;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('UpdateAccount action updates amount and currency and dispatches AccountUpdated', function () {
    Event::fake([AccountUpdated::class]);

    $account = Account::factory()->main()->create([
        'currency' => Currency::TND,
        'amount' => 100,
    ]);

    $updated = app(UpdateAccount::class)->execute($account, Currency::EUR, 1234.56);

    expect($updated->fresh())
        ->currency->toBe(Currency::EUR)
        ->amount->toBe('1234.56');

    Event::assertDispatched(AccountUpdated::class, fn (AccountUpdated $event) => $event->account->is($account));
});

test('owner can update their account via the controller', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $account = Account::factory()->main()->for($entity)->create([
        'currency' => Currency::TND,
        'amount' => 0,
    ]);

    $this->actingAs($user)
        ->put(route('accounts.update', $account), [
            'currency' => 'EUR',
            'amount' => '250.75',
        ])
        ->assertRedirect();

    expect($account->fresh())
        ->currency->toBe(Currency::EUR)
        ->amount->toBe('250.75');
});

test('owner cannot update another users account', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $entity = Entity::factory()->llc()->for($owner)->create();
    $account = Account::factory()->main()->for($entity)->create([
        'currency' => Currency::TND,
        'amount' => 100,
    ]);

    $this->actingAs($intruder)
        ->put(route('accounts.update', $account), [
            'currency' => 'EUR',
            'amount' => '999',
        ])
        ->assertForbidden();

    expect($account->fresh())
        ->currency->toBe(Currency::TND)
        ->amount->toBe('100.00');
});

test('update validates currency and amount', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->llc()->for($user)->create();
    $account = Account::factory()->main()->for($entity)->create();

    $this->actingAs($user)
        ->put(route('accounts.update', $account), [
            'currency' => 'XYZ',
            'amount' => 'not-a-number',
        ])
        ->assertSessionHasErrors(['currency', 'amount']);
});

test('registering a user provisions a TND main account', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration is not enabled.');
    }

    $this->post('/register', [
        'name' => 'Bilel',
        'email' => 'bilel-tnd@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $user = User::query()->where('email', 'bilel-tnd@example.test')->firstOrFail();
    $personal = $user->entities()->firstOrFail();

    expect($personal->mainAccount)
        ->currency->toBe(Currency::TND)
        ->amount->toBe('0.00');
});
