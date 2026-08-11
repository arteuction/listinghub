<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
| 3.5.0 — admin user management.
|
| Key invariants:
|   • Only an admin reaches these routes.
|   • Roles and status are editable; passwords deliberately are NOT.
|   • Self-lockout guards: an admin can neither suspend themselves nor drop
|     their own admin role (both are unrecoverable without DB access).
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function userAdmin(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('admin');

    return $user;
}

function plainMember(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('member');

    return $user;
}

it('lists users and filters by the search term', function () {
    $admin = userAdmin();
    User::factory()->create(['name' => 'Търсен Потребител', 'email' => 'findme@example.com']);
    User::factory()->create(['name' => 'Друг', 'email' => 'other@example.com']);

    $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'findme']))
        ->assertOk()
        ->assertSee('findme@example.com')
        ->assertDontSee('other@example.com');
});

it('updates a user\'s name, email, status and roles', function () {
    $admin = userAdmin();
    $target = plainMember();

    $this->actingAs($admin)->put(route('admin.users.update', $target), [
        'name' => 'Ново Име',
        'email' => 'new@example.com',
        'status' => 'suspended',
        'roles' => ['staff'],
    ])->assertRedirect(route('admin.users.index'));

    $target->refresh();
    expect($target->name)->toBe('Ново Име')
        ->and($target->email)->toBe('new@example.com')
        ->and($target->status)->toBe(UserStatus::Suspended)
        ->and($target->hasRole('staff'))->toBeTrue()
        ->and($target->hasRole('member'))->toBeFalse();
});

it('rejects an email already taken by another user', function () {
    $admin = userAdmin();
    $target = plainMember();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)->put(route('admin.users.update', $target), [
        'name' => $target->name,
        'email' => 'taken@example.com',
        'status' => 'active',
    ])->assertSessionHasErrors('email');
});

it('allows a user to keep their own email (unique rule ignores self)', function () {
    $admin = userAdmin();
    $target = plainMember();

    $this->actingAs($admin)->put(route('admin.users.update', $target), [
        'name' => 'Същият Имейл',
        'email' => $target->email,
        'status' => 'active',
    ])->assertRedirect()->assertSessionHasNoErrors();
});

it('stops an admin from suspending their own account', function () {
    $admin = userAdmin();

    $this->actingAs($admin)->put(route('admin.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'status' => 'suspended',
        'roles' => ['admin'],
    ])->assertSessionHasErrors('status');

    expect($admin->fresh()->status)->toBe(UserStatus::Active);
});

it('stops an admin from removing their own admin role', function () {
    $admin = userAdmin();

    $this->actingAs($admin)->put(route('admin.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'status' => 'active',
        'roles' => ['member'],
    ])->assertSessionHasErrors('roles');

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('lets an admin suspend a DIFFERENT admin', function () {
    $admin = userAdmin();
    $other = userAdmin();

    $this->actingAs($admin)->put(route('admin.users.update', $other), [
        'name' => $other->name,
        'email' => $other->email,
        'status' => 'suspended',
        'roles' => ['admin'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($other->fresh()->status)->toBe(UserStatus::Suspended);
});

it('blocks a plain member from user management', function () {
    $member = plainMember();
    $target = plainMember();

    $this->actingAs($member)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.users.edit', $target))->assertForbidden();
    $this->actingAs($member)->put(route('admin.users.update', $target), [
        'name' => 'x', 'email' => 'x@example.com', 'status' => 'active',
    ])->assertForbidden();
});

it('does not accept a password field', function () {
    $admin = userAdmin();
    $target = plainMember();
    $originalHash = $target->password;

    $this->actingAs($admin)->put(route('admin.users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'status' => 'active',
        'password' => 'attacker-chosen-password',
    ])->assertRedirect();

    // The field is not in the request rules, so it is never applied.
    expect($target->fresh()->password)->toBe($originalHash);
});
