<?php

declare(strict_types=1);

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists all menus for admin', function () {
    Menu::query()->create(['handle' => 'header', 'name' => 'Header']);
    Menu::query()->create(['handle' => 'footer', 'name' => 'Footer']);

    $this->actingAs($this->admin)
        ->get(route('admin.menus.index'))
        ->assertOk()
        ->assertSeeText('Header')
        ->assertSeeText('Footer');
});

it('shows the menu edit form', function () {
    $menu = Menu::query()->create(['handle' => 'header', 'name' => 'Header']);

    $this->actingAs($this->admin)
        ->get(route('admin.menus.edit', $menu))
        ->assertOk()
        ->assertSeeText('Header');
});

it('syncs menu items via the update endpoint', function () {
    $menu = Menu::query()->create(['handle' => 'main', 'name' => 'Main Nav']);

    $this->actingAs($this->admin)
        ->put(route('admin.menus.update', $menu), [
            'items' => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'About', 'url' => '/about'],
            ],
        ])
        ->assertRedirect();

    expect($menu->fresh()->items)->toHaveCount(2);
});

it('replaces existing items on re-sync', function () {
    $menu = Menu::query()->create(['handle' => 'test', 'name' => 'Test']);

    $this->actingAs($this->admin)->put(route('admin.menus.update', $menu), [
        'items' => [
            ['label' => 'A', 'url' => '/a'],
            ['label' => 'B', 'url' => '/b'],
        ],
    ]);

    $this->actingAs($this->admin)->put(route('admin.menus.update', $menu), [
        'items' => [
            ['label' => 'C', 'url' => '/c'],
        ],
    ]);

    $items = $menu->fresh()->items;
    expect($items)->toHaveCount(1)
        ->and($items->first()->label)->toBe('C');
});

it('denies menu editing to a member', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $menu = Menu::query()->create(['handle' => 'restricted', 'name' => 'Restricted']);

    $this->actingAs($member)
        ->get(route('admin.menus.edit', $menu))
        ->assertForbidden();
});

it('denies menu editing to a guest', function () {
    $menu = Menu::query()->create(['handle' => 'guest-test', 'name' => 'Guest Test']);

    $this->get(route('admin.menus.edit', $menu))
        ->assertRedirect('/login');
});
