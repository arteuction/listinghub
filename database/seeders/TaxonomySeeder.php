<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use Illuminate\Database\Seeder;

/**
 * Seeds the core taxonomy vocabularies and bridges existing Category rows
 * to TaxonomyTerms so that listings.category_id keeps working during the
 * transition period (3.6.0D compatibility layer).
 *
 * Safe to run multiple times — uses firstOrCreate on slugs.
 */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Listing categories — hierarchical, single-select
        $listingCats = Taxonomy::query()->firstOrCreate(
            ['slug' => 'listing-categories'],
            [
                'name' => 'Категории обяви',
                'context' => 'listings',
                'is_hierarchical' => true,
                'allow_multiple' => false,
                'icon_type' => 'emoji',
            ]
        );

        // 2. Listing amenities/tags — flat, multi-select
        Taxonomy::query()->firstOrCreate(
            ['slug' => 'listing-amenities'],
            [
                'name' => 'Удобства',
                'context' => 'listings',
                'is_hierarchical' => false,
                'allow_multiple' => true,
                'icon_type' => 'emoji',
            ]
        );

        // 3. Bridge existing Category rows to TaxonomyTerms.
        //    Only top-level categories (parent_id = null) first, then children,
        //    so parent_id on TaxonomyTerm can be set correctly.
        $this->bridgeCategories($listingCats);
    }

    private function bridgeCategories(Taxonomy $taxonomy): void
    {
        $this->bridgeLevel(null, null, $taxonomy);
    }

    private function bridgeLevel(?int $categoryParentId, ?int $termParentId, Taxonomy $taxonomy): void
    {
        $categories = Category::query()
            ->where('parent_id', $categoryParentId)
            ->orderBy('sort_order')
            ->get();

        foreach ($categories as $category) {
            if ($category->taxonomy_term_id !== null) {
                // Already bridged — recurse to children
                $this->bridgeLevel($category->id, $category->taxonomy_term_id, $taxonomy);

                continue;
            }

            $term = TaxonomyTerm::query()->firstOrCreate(
                ['taxonomy_id' => $taxonomy->id, 'slug' => $category->slug],
                [
                    'parent_id' => $termParentId,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'image_path' => $category->image_path,
                    'sort_order' => $category->sort_order,
                    'status' => $category->is_active ? 'published' : 'archived',
                ]
            );

            $category->update(['taxonomy_term_id' => $term->id]);

            $this->bridgeLevel($category->id, $term->id, $taxonomy);
        }
    }
}
