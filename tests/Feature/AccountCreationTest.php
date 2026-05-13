<?php

use App\Actions\CreateAccount;
use App\Enums\Currency;
use App\Enums\EntityColor;
use App\Enums\EntityType;
use App\Events\AccountCreated;
use App\Models\Account;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('CreateAccount action creates an account and dispatches AccountCreated', function () {
    Event::fake([AccountCreated::class]);

    $entity = Entity::factory()->llc()->create();

    $account = app(CreateAccount::class)->execute($entity, 'Operating', Currency::EUR, isMain: true);

    expect($account)
        ->name->toBe('Operating')
        ->currency->toBe(Currency::EUR)
        ->is_main->toBeTrue()
        ->entity_id->toBe($entity->id);

    Event::assertDispatched(AccountCreated::class, fn (AccountCreated $event) => $event->account->is($account));
});

test('first account on an entity is auto-promoted to main', function () {
    $entity = Entity::factory()->llc()->create();

    $first = app(CreateAccount::class)->execute($entity, 'Checking', Currency::USD);

    expect($first->is_main)->toBeTrue();
});

test('subsequent accounts default to non-main', function () {
    $entity = Entity::factory()->llc()->create();
    Account::factory()->main()->for($entity)->create();

    $second = app(CreateAccount::class)->execute($entity, 'Savings', Currency::USD);

    expect($second->is_main)->toBeFalse();
});

test('an entity cannot have two main accounts', function () {
    $entity = Entity::factory()->llc()->create();
    Account::factory()->main()->for($entity)->create();

    expect(fn () => Account::factory()->main()->for($entity)->create())
        ->toThrow(QueryException::class);
});

test('different entities can each have their own main account', function () {
    $a = Entity::factory()->llc()->create();
    $b = Entity::factory()->llc()->create();

    Account::factory()->main()->for($a)->create();
    Account::factory()->main()->for($b)->create();

    expect($a->mainAccount()->exists())->toBeTrue()
        ->and($b->mainAccount()->exists())->toBeTrue();
});

test('deleting an entity cascades to its accounts', function () {
    $entity = Entity::factory()->llc()->create();
    $account = Account::factory()->main()->for($entity)->create();

    $entity->delete();

    expect(Account::query()->whereKey($account->id)->exists())->toBeFalse();
});

test('creating an LLC via the wizard persists the submitted main account', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(route('entities.store'), [
        'name' => 'Acme LLC',
        'color' => 'blue',
        'accounts' => [
            ['name' => 'Operating', 'currency' => 'EUR', 'is_main' => true],
        ],
    ])->assertRedirect(route('entities.index'));

    $llc = $user->entities()->where('type', EntityType::LLC)->firstOrFail();

    expect($llc->mainAccount)
        ->not->toBeNull()
        ->name->toBe('Operating')
        ->currency->toBe(Currency::EUR)
        ->is_main->toBeTrue();
});

test('the wizard can create an LLC with multiple accounts', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(route('entities.store'), [
        'name' => 'Globex',
        'color' => 'purple',
        'accounts' => [
            ['name' => 'Operating', 'currency' => 'USD', 'is_main' => true],
            ['name' => 'Reserve', 'currency' => 'EUR', 'is_main' => false],
        ],
    ])->assertRedirect(route('entities.index'));

    $llc = $user->entities()->where('type', EntityType::LLC)->firstOrFail();

    expect($llc->accounts()->count())->toBe(2)
        ->and($llc->mainAccount->name)->toBe('Operating')
        ->and($llc->accounts()->where('is_main', false)->first())
        ->name->toBe('Reserve')
        ->currency->toBe(Currency::EUR);
});

test('the wizard requires at least one account', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(route('entities.store'), [
        'name' => 'Empty Co',
        'color' => 'red',
        'accounts' => [],
    ])->assertSessionHasErrors(['accounts']);

    expect($user->entities()->where('name', 'Empty Co')->exists())->toBeFalse();
});

test('the wizard rejects zero main accounts', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(route('entities.store'), [
        'name' => 'No Main Co',
        'color' => 'red',
        'accounts' => [
            ['name' => 'Operating', 'currency' => 'USD', 'is_main' => false],
        ],
    ])->assertSessionHasErrors(['accounts']);

    expect($user->entities()->where('name', 'No Main Co')->exists())->toBeFalse();
});

test('the wizard rejects multiple main accounts', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(route('entities.store'), [
        'name' => 'Two Main Co',
        'color' => 'red',
        'accounts' => [
            ['name' => 'A', 'currency' => 'USD', 'is_main' => true],
            ['name' => 'B', 'currency' => 'EUR', 'is_main' => true],
        ],
    ])->assertSessionHasErrors(['accounts']);

    expect($user->entities()->where('name', 'Two Main Co')->exists())->toBeFalse();
});

test('the wizard validates currency values', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(route('entities.store'), [
        'name' => 'Bad Currency Co',
        'color' => 'red',
        'accounts' => [
            ['name' => 'Main', 'currency' => 'XYZ', 'is_main' => true],
        ],
    ])->assertSessionHasErrors(['accounts.0.currency']);
});

test('registering a user provisions a TND main account on the personal entity', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration is not enabled.');
    }

    $this->post('/register', [
        'name' => 'Bilel',
        'email' => 'bilel-accounts@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $user = User::query()->where('email', 'bilel-accounts@example.test')->firstOrFail();
    $personal = $user->entities()->where('type', EntityType::PERSONAL)->firstOrFail();

    expect($personal->mainAccount)
        ->not->toBeNull()
        ->name->toBe('Main')
        ->currency->toBe(Currency::TND)
        ->is_main->toBeTrue();
});

test('the wizard accepts an initial amount per account', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(route('entities.store'), [
        'name' => 'Funded LLC',
        'color' => 'blue',
        'accounts' => [
            ['name' => 'Operating', 'currency' => 'TND', 'amount' => '1500.50', 'is_main' => true],
        ],
    ])->assertRedirect(route('entities.index'));

    $llc = $user->entities()->where('name', 'Funded LLC')->firstOrFail();

    expect($llc->mainAccount)
        ->amount->toBe('1500.50')
        ->currency->toBe(Currency::TND);
});

test('entity color enum still works alongside accounts', function () {
    $entity = Entity::factory()->create(['color' => EntityColor::PURPLE]);
    Account::factory()->main()->for($entity)->create();

    expect($entity->fresh()->color)->toBe(EntityColor::PURPLE)
        ->and($entity->fresh()->mainAccount)->not->toBeNull();
});
