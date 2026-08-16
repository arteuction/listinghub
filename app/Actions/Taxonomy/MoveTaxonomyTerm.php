<?php

declare(strict_types=1);

namespace App\Actions\Taxonomy;

use App\Models\TaxonomyTerm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves a term to a new parent (or to root level when $newParent is null).
 *
 * Guards against:
 *  - placing a term under itself
 *  - placing a term under one of its own descendants (cycle)
 *  - crossing taxonomy boundaries
 */
final class MoveTaxonomyTerm
{
    public function handle(
        TaxonomyTerm $term,
        ?TaxonomyTerm $newParent,
        ?User $actor = null,
    ): TaxonomyTerm {
        if ($newParent === null) {
            return $this->doMove($term, null, $actor);
        }

        if ($newParent->id === $term->id) {
            throw new InvalidArgumentException('A taxonomy term cannot be its own parent.');
        }

        if ($newParent->taxonomy_id !== $term->taxonomy_id) {
            throw new InvalidArgumentException('Cannot move a term to a parent in a different taxonomy.');
        }

        if ($this->isDescendant($newParent, $term)) {
            throw new InvalidArgumentException(
                'Cannot place a taxonomy term under one of its own descendants (cycle detected).'
            );
        }

        return $this->doMove($term, $newParent->id, $actor);
    }

    private function doMove(TaxonomyTerm $term, ?int $newParentId, ?User $actor): TaxonomyTerm
    {
        return DB::transaction(function () use ($term, $newParentId, $actor): TaxonomyTerm {
            $term->parent_id  = $newParentId;
            $term->updated_by = $actor?->getKey();
            $term->save();

            return $term;
        });
    }

    /**
     * Returns true when $candidate is an ancestor of $potentialDescendant.
     * We walk UP from $potentialDescendant looking for $candidate.
     */
    private function isDescendant(TaxonomyTerm $potentialDescendant, TaxonomyTerm $candidate): bool
    {
        $current = $potentialDescendant;

        while ($current->parent_id !== null) {
            if ($current->parent_id === $candidate->id) {
                return true;
            }

            $current = TaxonomyTerm::query()->findOrFail($current->parent_id);
        }

        return false;
    }
}
