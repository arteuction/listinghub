<?php

declare(strict_types=1);

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');

    $this->member = User::factory()->create();
    $this->member->assignRole('member');
});

// --- Policy: admin can manage categories ---

it('admin can view categories index', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        ->assertOk();
});

it('admin can create a category', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'name' => 'Тест',
            'slug' => 'test',
            'sort_order' => 0,
        ])
        ->assertRedirect();

    expect(Category::where('slug', 'test')->exists())->toBeTrue();
});

it('admin can update a category', function () {
    $category = Category::factory()->create(['name' => 'Стара']);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), [
            'name' => 'Нова',
            'slug' => $category->slug,
            'sort_order' => 0,
        ])
        ->assertRedirect();

    expect($category->refresh()->name)->toBe('Нова');
});

it('admin can delete an empty category', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect();

    expect(Category::find($category->id))->toBeNull();
});

// --- Policy: staff cannot manage categories ---

it('staff cannot access categories index', function () {
    $this->actingAs($this->staff)
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

it('staff cannot create a category', function () {
    $this->actingAs($this->staff)
        ->post(route('admin.categories.store'), [
            'name' => 'Тест',
            'slug' => 'test',
            'sort_order' => 0,
        ])
        ->assertForbidden();
});

// --- Policy: member cannot manage categories ---

it('member cannot access categories', function () {
    $this->actingAs($this->member)
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

// --- Shared actions: circular parent ---

it('rejects circular parent via shared action', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    app(UpdateCategory::class)->handle($parent, [
        'parent_id' => $child->id,
        'name' => $parent->name,
        'slug' => $parent->slug,
        'sort_order' => $parent->sort_order,
    ]);
})->throws(ValidationException::class);

// --- Shared actions: delete guards ---

it('rejects delete of category with children via shared action', function () {
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);

    app(DeleteCategory::class)->handle($parent);
})->throws(ValidationException::class);

it('rejects delete of category with listings via shared action', function () {
    $category = Category::factory()->create();
    Listing::factory()->create(['category_id' => $category->id]);

    app(DeleteCategory::class)->handle($category);
})->throws(ValidationException::class);

// --- Shared actions: create ---

it('creates a category via shared action', function () {
    $category = app(CreateCategory::class)->handle([
        'name' => 'Нова категория',
        'slug' => 'nova-kategoriya',
        'sort_order' => 5,
        'is_active' => true,
    ]);

    expect($category->name)->toBe('Нова категория')
        ->and($category->slug)->toBe('nova-kategoriya')
        ->and($category->sort_order)->toBe(5)
        ->and($category->is_active)->toBeTrue();
});
