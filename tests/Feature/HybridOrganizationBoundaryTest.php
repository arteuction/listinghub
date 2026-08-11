<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

const BOUNDARY_TABLES = ['users', 'listings', 'products', 'plans', 'subscriptions'];

it('has an organization_id column on all five boundary tables', function () {
    foreach (BOUNDARY_TABLES as $table) {
        expect(Schema::hasColumn($table, 'organization_id'))->toBeTrue("{$table}.organization_id missing");
    }
});

it('keeps organization_id nullable on all five boundary tables', function () {
    foreach (BOUNDARY_TABLES as $table) {
        $nullable = collect(Schema::getColumns($table))
            ->firstWhere('name', 'organization_id')['nullable'] ?? null;
        expect($nullable)->toBeTrue("{$table}.organization_id should be nullable");
    }
});

it('enforces a real foreign key on all five boundary tables', function (string $model) {
    // Behavioural proof (works on :memory:, unlike schema introspection):
    // a non-existent organization_id must be rejected by the live FK.
    $model::factory()->create(['organization_id' => 999999]);
})->with([
    'users'         => [App\Models\User::class],
    'listings'      => [App\Models\Listing::class],
    'products'      => [App\Models\Product::class],
    'plans'         => [App\Models\Plan::class],
    'subscriptions' => [App\Models\Subscription::class],
])->throws(QueryException::class);

it('creates shared-catalog records with a null organization_id', function () {
    expect(User::factory()->create()->organization_id)->toBeNull();
    expect(Listing::factory()->create()->organization_id)->toBeNull();
    expect(Product::factory()->create()->organization_id)->toBeNull();
});

it('rejects an organization_id that does not exist (FK is live)', function () {
    Listing::factory()->create(['organization_id' => 999999]);
})->throws(QueryException::class);

it('applies no organization global scope — every listing is visible', function () {
    Listing::factory()->count(3)->create();
    Listing::factory()->create(['organization_id' => Organization::factory()]);

    expect(Listing::query()->count())->toBe(4);
});
