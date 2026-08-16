<?php

declare(strict_types=1);

namespace App\Actions\Taxonomy;

use App\Models\TaxonomyTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Attaches a taxonomy term to a model instance.
 *
 * Enforces the taxonomy's allow_multiple policy: when allow_multiple is false
 * any existing term from the same taxonomy is replaced atomically.
 */
final class AttachTermToModel
{
    public function handle(TaxonomyTerm $term, Model $model): void
    {
        DB::transaction(function () use ($term, $model): void {
            $taxonomy = $term->taxonomy;
            $morphType = $model->getMorphClass();
            $morphId = (int) $model->getKey();

            if (! $taxonomy->allow_multiple) {
                // Remove any existing term from the same taxonomy on this model.
                DB::table('taxonomy_termables')
                    ->whereIn('taxonomy_term_id', function ($q) use ($taxonomy) {
                        $q->select('id')
                            ->from('taxonomy_terms')
                            ->where('taxonomy_id', $taxonomy->id);
                    })
                    ->where('termable_type', $morphType)
                    ->where('termable_id', $morphId)
                    ->delete();
            }

            // Upsert — idempotent attach.
            DB::table('taxonomy_termables')->insertOrIgnore([
                'taxonomy_term_id' => $term->id,
                'termable_type' => $morphType,
                'termable_id' => $morphId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
