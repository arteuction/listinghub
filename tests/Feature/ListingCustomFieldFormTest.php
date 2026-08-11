<?php

declare(strict_types=1);

use App\Enums\CustomFieldType;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

/*
| 3.0C — custom fields in the admin listing create/edit form. The custom-field
| payload is applied ONLY through SyncListingCustomFields; the controller never
| touches CustomFieldValue directly.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->create(['email' => 'admin@example.com', 'password' => Hash::make('x'), 'status' => UserStatus::Active]);
    $admin->assignRole('admin');
    $this->admin = $admin;
    $this->actingAs($admin);

    $this->cat = Category::factory()->create();
    $this->area = CustomField::factory()->create(['category_id' => $this->cat->id, 'key' => 'area', 'type' => CustomFieldType::Number, 'filterable' => true]);
    $this->note = CustomField::factory()->create(['category_id' => $this->cat->id, 'key' => 'note', 'type' => CustomFieldType::Text, 'sort_order' => 1]);
});

afterEach(fn () => @unlink(storage_path('app/installed.lock')));

it('renders the create form with the category fields', function () {
    $this->get(route('admin.listings.create', $this->cat))
        ->assertOk()
        ->assertSee('area')
        ->assertSee('note');
});

it('creates a listing and syncs custom field values', function () {
    $this->post(route('admin.listings.store'), [
        'title' => 'My place', 'category_id' => $this->cat->id,
        'custom_fields' => ['area' => '12.5', 'note' => 'hello'],
    ])->assertRedirect(route('admin.dashboard'));

    $listing = Listing::query()->where('title', 'My place')->firstOrFail();
    expect((float) $listing->customFieldValues()->where('custom_field_id', $this->area->id)->value('value_decimal'))->toBe(12.5);
    expect($listing->customFieldValues()->where('custom_field_id', $this->note->id)->value('value_text'))->toBe('hello');
});

it('returns a per-field error and creates nothing when a required field is missing', function () {
    $this->area->update(['is_required' => true]);

    $res = $this->from(route('admin.listings.create', $this->cat))->post(route('admin.listings.store'), [
        'title' => 'Bad', 'category_id' => $this->cat->id,
        'custom_fields' => ['note' => 'x'], // area missing
    ]);

    $res->assertSessionHasErrors('custom_fields.area');
    expect(Listing::query()->where('title', 'Bad')->exists())->toBeFalse(); // atomic: nothing created
});

it('returns a per-field error for an invalid value', function () {
    $res = $this->from(route('admin.listings.create', $this->cat))->post(route('admin.listings.store'), [
        'title' => 'Bad2', 'category_id' => $this->cat->id,
        'custom_fields' => ['area' => 'not-a-number'],
    ]);

    $res->assertSessionHasErrors('custom_fields.area');
    expect(Listing::query()->where('title', 'Bad2')->exists())->toBeFalse();
});

it('edits and full-replaces values (an omitted optional field is cleared)', function () {
    $listing = Listing::factory()->create(['category_id' => $this->cat->id, 'user_id' => $this->admin->id]);
    CustomFieldValue::create(['custom_field_id' => $this->area->id, 'listing_id' => $listing->id, 'value_decimal' => '9']);
    CustomFieldValue::create(['custom_field_id' => $this->note->id, 'listing_id' => $listing->id, 'value_text' => 'keep']);

    $this->get(route('admin.listings.edit', $listing))->assertOk();

    // Submit only 'note' — 'area' is omitted → cleared (full replacement).
    $this->put(route('admin.listings.update', $listing), [
        'title' => $listing->title, 'category_id' => $this->cat->id,
        'custom_fields' => ['note' => 'changed'],
    ])->assertRedirect(route('admin.dashboard'));

    expect($listing->customFieldValues()->where('custom_field_id', $this->area->id)->exists())->toBeFalse();
    expect($listing->customFieldValues()->where('custom_field_id', $this->note->id)->value('value_text'))->toBe('changed');
});

it('purges old-category values when the category changes', function () {
    $listing = Listing::factory()->create(['category_id' => $this->cat->id, 'user_id' => $this->admin->id]);
    CustomFieldValue::create(['custom_field_id' => $this->area->id, 'listing_id' => $listing->id, 'value_decimal' => '9']);

    $catB = Category::factory()->create();
    $color = CustomField::factory()->create(['category_id' => $catB->id, 'key' => 'color', 'type' => CustomFieldType::Select, 'options' => [['value' => 'red', 'label' => 'Red']]]);

    $this->put(route('admin.listings.update', $listing), [
        'title' => $listing->title, 'category_id' => $catB->id,
        'custom_fields' => ['color' => 'red'],
    ])->assertRedirect(route('admin.dashboard'));

    expect($listing->fresh()->category_id)->toBe($catB->id);
    // old category value gone, new one present
    expect(CustomFieldValue::query()->where('custom_field_id', $this->area->id)->exists())->toBeFalse();
    expect($listing->customFieldValues()->where('custom_field_id', $color->id)->value('value_string'))->toBe('red');
});

it('blocks a member without manage settings', function () {
    $member = User::factory()->create(['email' => 'm@example.com', 'status' => UserStatus::Active]);
    $member->assignRole('member');

    $this->actingAs($member)->get(route('admin.listings.create', $this->cat))->assertForbidden();
});
