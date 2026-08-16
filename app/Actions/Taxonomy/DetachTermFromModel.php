<?php

declare(strict_types=1);

namespace App\Actions\Taxonomy;

use App\Models\TaxonomyTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DetachTermFromModel
{
    public function handle(TaxonomyTerm $term, Model $model): void
    {
        DB::table('taxonomy_termables')
            ->where('taxonomy_term_id', $term->id)
            ->where('termable_type', $model->getMorphClass())
            ->where('termable_id', (int) $model->getKey())
            ->delete();
    }
}
