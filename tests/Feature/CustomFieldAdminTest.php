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
| 3.0B — admin custom-field definitions. Requires the 3.0A auth shell: an admin
| with `manage settings`. All mutations must go through the domain actions, so
| an invariant violation surfaces as a per-field ("definition") error.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('secret-password'),
        'status' => UserStatus::Active,
    ]);
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $this->category = Category::factory()->create();
});

afterEach(fn () => @unlink(storage_path('app/installed.lock')));

function payload(array $overrides = []): array
{
    return array_merge([
        'label' => 'Area', 'key' => 'area', 'type' => CustomFieldType::Number->value,
        'is_required' => false, 'searchable' => false, 'filterable' => true, 'sortable' => true,
        'sort_order' => 10,
    ], $overrides);
}

it('renders the index, create and edit screens', function () {
    $field = CustomField::factory()->create(['category_id' => $this->category->id, 'type' => CustomFieldType::Text]);

    $this->get(route('admin.custom-fields.index', $this->category))->assertOk();
    $this->get(route('admin.custom-fields.create', $this->category))->assertOk();
    $this->get(route('admin.custom-fields.edit', [$this->category, $field]))->assertOk();
});

it('creates a valid field through the action', function () {
    $this->post(route('admin.custom-fields.store', $this->category), payload())
        ->assertRedirect(route('admin.custom-fields.index', $this->category));

    $field = CustomField::query()->where('key', 'area')->first();
    expect($field)->not->toBeNull()
        ->and($field->type)->toBe(CustomFieldType::Number)
        ->and($field->filterable)->toBeTrue();
});

it('rejects a flag that the type does not support (I10) with a per-field error', function () {
    // text cannot be sortable
    $res = $this->from(route('admin.custom-fields.create', $this->category))
        ->post(route('admin.custom-fields.store', $this->category), payload(['type' => CustomFieldType::Text->value, 'key' => 'note', 'sortable' => true]));

    $res->assertRedirect(route('admin.custom-fields.create', $this->category));
    $res->assertSessionHasErrors('definition');
    expect(CustomField::query()->where('key', 'note')->exists())->toBeFalse();
});

it('rejects a select without options (I11)', function () {
    $res = $this->post(route('admin.custom-fields.store', $this->category), payload(['type' => CustomFieldType::Select->value, 'key' => 'color', 'sortable' => false, 'filterable' => true]));

    $res->assertSessionHasErrors('definition');
    expect(CustomField::query()->where('key', 'color')->exists())->toBeFalse();
});

it('rejects a duplicate key within a category', function () {
    CustomField::factory()->create(['category_id' => $this->category->id, 'key' => 'area', 'type' => CustomFieldType::Number]);

    $res = $this->post(route('admin.custom-fields.store', $this->category), payload(['key' => 'area']));
    $res->assertSessionHasErrors('definition');
});

it('updates a label but forbids changing key/type once values exist', function () {
    $field = CustomField::factory()->create(['category_id' => $this->category->id, 'key' => 'area', 'type' => CustomFieldType::Number, 'filterable' => true]);
    $listing = Listing::factory()->create(['category_id' => $this->category->id]);
    CustomFieldValue::create(['custom_field_id' => $field->id, 'listing_id' => $listing->id, 'value_decimal' => '5']);

    // label change ok
    $this->put(route('admin.custom-fields.update', [$this->category, $field]), payload(['label' => 'Size', 'key' => 'area']))
        ->assertRedirect(route('admin.custom-fields.index', $this->category));
    expect($field->fresh()->label)->toBe('Size');

    // key change blocked
    $this->from(route('admin.custom-fields.edit', [$this->category, $field]))
        ->put(route('admin.custom-fields.update', [$this->category, $field]), payload(['key' => 'renamed']))
        ->assertSessionHasErrors('definition');
    expect($field->fresh()->key)->toBe('area');
});

it('lets a used select option label change but blocks removing the used value (I7/I8)', function () {
    $field = CustomField::factory()->create([
        'category_id' => $this->category->id, 'key' => 'color', 'type' => CustomFieldType::Select,
        'filterable' => true, 'options' => [['value' => 'red', 'label' => 'Red']],
    ]);
    $listing = Listing::factory()->create(['category_id' => $this->category->id]);
    CustomFieldValue::create(['custom_field_id' => $field->id, 'listing_id' => $listing->id, 'value_string' => 'red']);

    // relabel the used value -> ok
    $this->put(route('admin.custom-fields.update', [$this->category, $field]), [
        'label' => 'Colour', 'key' => 'color', 'type' => CustomFieldType::Select->value,
        'filterable' => true, 'options' => [['value' => 'red', 'label' => 'Crimson']],
    ])->assertRedirect(route('admin.custom-fields.index', $this->category));

    // remove the used value -> blocked
    $this->from(route('admin.custom-fields.edit', [$this->category, $field]))
        ->put(route('admin.custom-fields.update', [$this->category, $field]), [
            'label' => 'Colour', 'key' => 'color', 'type' => CustomFieldType::Select->value,
            'filterable' => true, 'options' => [['value' => 'blue', 'label' => 'Blue']],
        ])->assertSessionHasErrors('definition');
});

it('deletes a field with no values but refuses one in use', function () {
    $empty = CustomField::factory()->create(['category_id' => $this->category->id, 'key' => 'k1', 'type' => CustomFieldType::Text]);
    $used = CustomField::factory()->create(['category_id' => $this->category->id, 'key' => 'k2', 'type' => CustomFieldType::Number, 'filterable' => true]);
    $listing = Listing::factory()->create(['category_id' => $this->category->id]);
    CustomFieldValue::create(['custom_field_id' => $used->id, 'listing_id' => $listing->id, 'value_decimal' => '1']);

    $this->delete(route('admin.custom-fields.destroy', [$this->category, $empty]))
        ->assertRedirect(route('admin.custom-fields.index', $this->category));
    expect(CustomField::query()->whereKey($empty->id)->exists())->toBeFalse();

    $this->from(route('admin.custom-fields.index', $this->category))
        ->delete(route('admin.custom-fields.destroy', [$this->category, $used]))
        ->assertSessionHasErrors('definition');
    expect(CustomField::query()->whereKey($used->id)->exists())->toBeTrue(); // still there
});

it('404s a field that belongs to another category', function () {
    $other = Category::factory()->create();
    $foreign = CustomField::factory()->create(['category_id' => $other->id, 'type' => CustomFieldType::Text]);

    $this->get(route('admin.custom-fields.edit', [$this->category, $foreign]))->assertNotFound();
});

it('blocks a member without manage settings', function () {
    $member = User::factory()->create(['email' => 'member@example.com', 'status' => UserStatus::Active]);
    $member->assignRole('member');

    $this->actingAs($member)->get(route('admin.custom-fields.index', $this->category))->assertForbidden();
});
