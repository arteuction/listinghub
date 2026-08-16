<?php

declare(strict_types=1);

namespace App\Actions\Taxonomy;

use App\Enums\TaxonomyTermStatus;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateTaxonomyTerm
{
    public function handle(
        Taxonomy $taxonomy,
        string $name,
        string $slug,
        ?TaxonomyTerm $parent = null,
        ?User $actor = null,
        int $sortOrder = 1000,
    ): TaxonomyTerm {
        if ($parent !== null && ! $taxonomy->is_hierarchical) {
            throw new InvalidArgumentException(
                "Taxonomy \"{$taxonomy->slug}\" is not hierarchical; parent terms are not allowed."
            );
        }

        if ($parent !== null && $parent->taxonomy_id !== $taxonomy->id) {
            throw new InvalidArgumentException('Parent term belongs to a different taxonomy.');
        }

        return DB::transaction(function () use ($taxonomy, $name, $slug, $parent, $actor, $sortOrder): TaxonomyTerm {
            return TaxonomyTerm::query()->create([
                'taxonomy_id' => $taxonomy->id,
                'parent_id' => $parent?->id,
                'slug' => $slug,
                'name' => $name,
                'status' => TaxonomyTermStatus::Published,
                'sort_order' => max(0, $sortOrder),
                'created_by' => $actor?->getKey(),
                'updated_by' => $actor?->getKey(),
            ]);
        });
    }
}
