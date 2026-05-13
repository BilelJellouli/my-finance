<?php

use App\Enums\EntityColor;
use App\Enums\EntityType;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('guests cannot access entities list', function () {
    $this->get(route('entities.index'))->assertRedirect(route('login'));
});

test('user sees their personal entity created on registration', function () {
    $user = User::factory()->create();
    $user->entities()->create([
        'name' => 'Personal',
        'type' => EntityType::Personal,
        'color' => EntityColor::Green,
    ]);

    $this->actingAs($user)
        ->get(route('entities.index'))
        ->assertOk();

    expect($user->entities()->count())->toBe(1)
        ->and($user->entities()->first()->type)->toBe(EntityType::Personal)
        ->and($user->entities()->first()->color)->toBe(EntityColor::Green);
});

test('user can create an LLC entity', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)
        ->post(route('entities.store'), [
            'name' => 'Acme LLC',
            'color' => 'blue',
        ])
        ->assertRedirect(route('entities.index'));

    expect($user->entities()->where('type', EntityType::Llc)->first())
        ->name->toBe('Acme LLC')
        ->color->toBe(EntityColor::Blue);
});

test('store requires name and a valid color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('entities.store'), ['name' => '', 'color' => 'neon'])
        ->assertSessionHasErrors(['name', 'color']);
});

test('the form cannot create a second personal entity (always stores as LLC)', function () {
    $user = User::factory()->create();
    Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)->post(route('entities.store'), [
        'name' => 'Another personal',
        'color' => 'red',
    ])->assertRedirect(route('entities.index'));

    expect($user->entities()->where('type', EntityType::Personal)->count())->toBe(1)
        ->and($user->entities()->where('type', EntityType::Llc)->where('name', 'Another personal')->exists())->toBeTrue();
});

test('user can edit their LLC entity', function () {
    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create(['name' => 'Old', 'color' => EntityColor::Blue]);

    $this->actingAs($user)
        ->put(route('entities.update', $llc), ['name' => 'New', 'color' => 'purple'])
        ->assertRedirect(route('entities.index'));

    expect($llc->refresh())
        ->name->toBe('New')
        ->color->toBe(EntityColor::Purple);
});

test('user cannot delete their personal entity', function () {
    $user = User::factory()->create();
    $personal = Entity::factory()->personal()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('entities.destroy', $personal))
        ->assertForbidden();

    expect(Entity::query()->whereKey($personal->id)->exists())->toBeTrue();
});

test('user can delete their LLC entity', function () {
    $user = User::factory()->create();
    $llc = Entity::factory()->llc()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('entities.destroy', $llc))
        ->assertRedirect(route('entities.index'));

    expect(Entity::query()->whereKey($llc->id)->exists())->toBeFalse();
});

test('user cannot edit or delete another users entity', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $llc = Entity::factory()->llc()->for($owner)->create();

    $this->actingAs($intruder)
        ->put(route('entities.update', $llc), ['name' => 'Hacked', 'color' => 'red'])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('entities.destroy', $llc))
        ->assertForbidden();

    expect($llc->refresh()->name)->not->toBe('Hacked');
});

test('registering a new user provisions a green personal entity', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration is not enabled.');
    }

    $this->post('/register', [
        'name' => 'Bilel',
        'email' => 'bilel@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $user = User::query()->where('email', 'bilel@example.test')->firstOrFail();

    expect($user->entities()->count())->toBe(1)
        ->and($user->entities()->first())
        ->type->toBe(EntityType::Personal)
        ->color->toBe(EntityColor::Green)
        ->name->toBe('Personal');
});
