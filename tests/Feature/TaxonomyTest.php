<?php

declare(strict_types=1);

use App\Actions\Taxonomy\AttachTermToModel;
use App\Actions\Taxonomy\CreateTaxonomyTerm;
use App\Actions\Taxonomy\DetachTermFromModel;
use App\Actions\Taxonomy\MoveTaxonomyTerm;
use App\Enums\TaxonomyTermStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Database\UniqueConstraintViolationException;

function makeTaxonomy(string $slug = 'test-tax', bool $hierarchical = true, bool $multiple = false, string $context = 'listings'): Taxonomy
{
    return Taxonomy::query()->create([
        'slug' => $slug,
        'name' => 'Test',
        'context' => $context,
        'is_hierarchical' => $hierarchical,
        'allow_multiple' => $multiple,
    ]);
}

// ─── CreateTaxonomyTerm ──────────────────────────────────────────────────────

it('creates a root-level term', function () {
    $tax = makeTaxonomy();
    $term = app(CreateTaxonomyTerm::class)->handle($tax, 'Недвижими имоти', 'real-estate');

    expect($term->taxonomy_id)->toBe($tax->id)
        ->and($term->parent_id)->toBeNull()
        ->and($term->status)->toBe(TaxonomyTermStatus::Published);
});

it('creates a child term under a parent', function () {
    $tax = makeTaxonomy();
    $parent = app(CreateTaxonomyTerm::class)->handle($tax, 'Parent', 'parent');
    $child = app(CreateTaxonomyTerm::class)->handle($tax, 'Child', 'child', $parent);

    expect($child->parent_id)->toBe($parent->id);
});

it('rejects a parent in a non-hierarchical taxonomy', function () {
    $tax = makeTaxonomy('flat-tax', hierarchical: false);
    $orphan = app(CreateTaxonomyTerm::class)->handle($tax, 'Root', 'root');

    expect(fn () => app(CreateTaxonomyTerm::class)->handle($tax, 'Child', 'child', $orphan))
        ->toThrow(InvalidArgumentException::class, 'hierarchical');
});

it('rejects a parent from a different taxonomy', function () {
    $tax1 = makeTaxonomy('tax-one');
    $tax2 = makeTaxonomy('tax-two');
    $parent = app(CreateTaxonomyTerm::class)->handle($tax1, 'Foreign', 'foreign');

    expect(fn () => app(CreateTaxonomyTerm::class)->handle($tax2, 'Child', 'child', $parent))
        ->toThrow(InvalidArgumentException::class, 'different taxonomy');
});

it('enforces unique slug within a taxonomy', function () {
    $tax = makeTaxonomy();
    app(CreateTaxonomyTerm::class)->handle($tax, 'First', 'same-slug');

    expect(fn () => app(CreateTaxonomyTerm::class)->handle($tax, 'Second', 'same-slug'))
        ->toThrow(UniqueConstraintViolationException::class);
});

// ─── MoveTaxonomyTerm ────────────────────────────────────────────────────────

it('moves a term to a new parent', function () {
    $tax = makeTaxonomy();
    $a = app(CreateTaxonomyTerm::class)->handle($tax, 'A', 'a');
    $b = app(CreateTaxonomyTerm::class)->handle($tax, 'B', 'b');

    app(MoveTaxonomyTerm::class)->handle($b, $a);

    expect($b->fresh()->parent_id)->toBe($a->id);
});

it('moves a term to root level', function () {
    $tax = makeTaxonomy();
    $a = app(CreateTaxonomyTerm::class)->handle($tax, 'A', 'a');
    $b = app(CreateTaxonomyTerm::class)->handle($tax, 'B', 'b', $a);

    app(MoveTaxonomyTerm::class)->handle($b, null);

    expect($b->fresh()->parent_id)->toBeNull();
});

it('rejects placing a term under itself', function () {
    $tax = makeTaxonomy();
    $term = app(CreateTaxonomyTerm::class)->handle($tax, 'Self', 'self');

    expect(fn () => app(MoveTaxonomyTerm::class)->handle($term, $term))
        ->toThrow(InvalidArgumentException::class, 'own parent');
});

it('rejects a cycle — placing a term under its own descendant', function () {
    $tax = makeTaxonomy();
    $root = app(CreateTaxonomyTerm::class)->handle($tax, 'Root', 'root');
    $mid = app(CreateTaxonomyTerm::class)->handle($tax, 'Mid', 'mid', $root);
    $leaf = app(CreateTaxonomyTerm::class)->handle($tax, 'Leaf', 'leaf', $mid);

    expect(fn () => app(MoveTaxonomyTerm::class)->handle($root, $leaf))
        ->toThrow(InvalidArgumentException::class, 'cycle');
});

it('rejects moving across taxonomy boundary', function () {
    $t1 = makeTaxonomy('t1');
    $t2 = makeTaxonomy('t2');
    $a = app(CreateTaxonomyTerm::class)->handle($t1, 'A', 'a');
    $b = app(CreateTaxonomyTerm::class)->handle($t2, 'B', 'b');

    expect(fn () => app(MoveTaxonomyTerm::class)->handle($a, $b))
        ->toThrow(InvalidArgumentException::class, 'different taxonomy');
});

// ─── Attach / Detach ────────────────────────────────────────────────────────

it('attaches a term to a listing', function () {
    $tax = makeTaxonomy('amenities', hierarchical: false, multiple: true);
    $term = app(CreateTaxonomyTerm::class)->handle($tax, 'Pool', 'pool');
    $listing = Listing::factory()->create();

    app(AttachTermToModel::class)->handle($term, $listing);

    $attached = DB::table('taxonomy_termables')
        ->where('taxonomy_term_id', $term->id)
        ->where('termable_type', $listing->getMorphClass())
        ->where('termable_id', $listing->id)
        ->exists();

    expect($attached)->toBeTrue();
});

it('attach is idempotent — does not create duplicate pivot rows', function () {
    $tax = makeTaxonomy('amens-idem', hierarchical: false, multiple: true);
    $term = app(CreateTaxonomyTerm::class)->handle($tax, 'Gym', 'gym');
    $listing = Listing::factory()->create();

    app(AttachTermToModel::class)->handle($term, $listing);
    app(AttachTermToModel::class)->handle($term, $listing);

    $count = DB::table('taxonomy_termables')
        ->where('taxonomy_term_id', $term->id)
        ->where('termable_id', $listing->id)
        ->count();

    expect($count)->toBe(1);
});

it('enforces allow_multiple = false by replacing the existing term', function () {
    $tax = makeTaxonomy('single-tax', multiple: false);
    $termA = app(CreateTaxonomyTerm::class)->handle($tax, 'Cat A', 'cat-a');
    $termB = app(CreateTaxonomyTerm::class)->handle($tax, 'Cat B', 'cat-b');
    $listing = Listing::factory()->create();

    app(AttachTermToModel::class)->handle($termA, $listing);
    app(AttachTermToModel::class)->handle($termB, $listing);

    $ids = DB::table('taxonomy_termables')
        ->where('termable_type', $listing->getMorphClass())
        ->where('termable_id', $listing->id)
        ->pluck('taxonomy_term_id')
        ->all();

    // Only termB should remain
    expect($ids)->toBe([$termB->id]);
});

it('detaches a term from a listing', function () {
    $tax = makeTaxonomy('dtax', hierarchical: false, multiple: true);
    $term = app(CreateTaxonomyTerm::class)->handle($tax, 'Sauna', 'sauna');
    $listing = Listing::factory()->create();

    app(AttachTermToModel::class)->handle($term, $listing);
    app(DetachTermFromModel::class)->handle($term, $listing);

    $exists = DB::table('taxonomy_termables')
        ->where('taxonomy_term_id', $term->id)
        ->where('termable_id', $listing->id)
        ->exists();

    expect($exists)->toBeFalse();
});

// ─── Bridge seeder ───────────────────────────────────────────────────────────

it('taxonomy seeder bridges existing categories to taxonomy terms', function () {
    // Create a couple of categories to bridge
    $cat = Category::query()->create([
        'name' => 'Ресторанти',
        'slug' => 'restaurants',
        'sort_order' => 1000,
        'is_active' => true,
    ]);

    $seeder = new TaxonomySeeder;
    $seeder->run();

    $cat->refresh();
    expect($cat->taxonomy_term_id)->not->toBeNull();

    $term = TaxonomyTerm::query()->find($cat->taxonomy_term_id);
    expect($term->slug)->toBe('restaurants')
        ->and($term->taxonomy->slug)->toBe('listing-categories');
});

it('taxonomy seeder is idempotent', function () {
    $seeder = new TaxonomySeeder;
    $seeder->run();
    $seeder->run();

    $taxCount = Taxonomy::query()->count();
    $termCount = TaxonomyTerm::query()->count();

    // Running twice must not duplicate rows
    $seeder->run();
    expect(Taxonomy::query()->count())->toBe($taxCount)
        ->and(TaxonomyTerm::query()->count())->toBe($termCount);
});
