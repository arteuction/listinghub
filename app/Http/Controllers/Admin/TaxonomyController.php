<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Taxonomy\CreateTaxonomyTerm;
use App\Actions\Taxonomy\MoveTaxonomyTerm;
use App\Http\Controllers\Controller;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxonomyController extends Controller
{
    public function __construct(
        private readonly CreateTaxonomyTerm $creator,
        private readonly MoveTaxonomyTerm $mover,
    ) {}

    public function index(): View
    {
        return view('admin.taxonomy.index', [
            'taxonomies' => Taxonomy::query()->withCount('terms')->orderBy('name')->get(),
        ]);
    }

    public function terms(Taxonomy $taxonomy): View
    {
        $terms = TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->whereNull('parent_id')
            ->with(['children.children'])
            ->withCount('listings')
            ->orderBy('sort_order')
            ->get();

        return view('admin.taxonomy.terms', [
            'taxonomy' => $taxonomy,
            'roots' => $terms,
        ]);
    }

    public function createTerm(Taxonomy $taxonomy): View
    {
        return view('admin.taxonomy.term-form', [
            'taxonomy' => $taxonomy,
            'term' => null,
            'parents' => $taxonomy->is_hierarchical
                ? TaxonomyTerm::query()
                    ->where('taxonomy_id', $taxonomy->id)
                    ->orderBy('name')
                    ->get()
                : collect(),
        ]);
    }

    public function storeTerm(Request $request, Taxonomy $taxonomy): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'parent_id' => ['nullable', 'integer', 'exists:taxonomy_terms,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:64'],
        ]);

        $parent = isset($data['parent_id'])
            ? TaxonomyTerm::query()->findOrFail($data['parent_id'])
            : null;

        $this->creator->handle(
            taxonomy: $taxonomy,
            name: $data['name'],
            slug: $data['slug'],
            parent: $parent,
            actor: $request->user(),
            sortOrder: (int) ($data['sort_order'] ?? 1000),
            icon: $data['icon'] ?? null,
        );

        return redirect()->route('admin.taxonomy.terms', $taxonomy)
            ->with('status', 'Терминът е добавен.');
    }

    public function editTerm(Taxonomy $taxonomy, TaxonomyTerm $term): View
    {
        return view('admin.taxonomy.term-form', [
            'taxonomy' => $taxonomy,
            'term' => $term,
            'parents' => $taxonomy->is_hierarchical
                ? TaxonomyTerm::query()
                    ->where('taxonomy_id', $taxonomy->id)
                    ->where('id', '!=', $term->id)
                    ->orderBy('name')
                    ->get()
                : collect(),
        ]);
    }

    public function updateTerm(Request $request, Taxonomy $taxonomy, TaxonomyTerm $term): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $term->fill([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 1000)),
        ])->save();

        return redirect()->route('admin.taxonomy.terms', $taxonomy)
            ->with('status', 'Терминът е обновен.');
    }

    public function moveTerm(Request $request, Taxonomy $taxonomy, TaxonomyTerm $term): RedirectResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:taxonomy_terms,id'],
        ]);

        $newParent = isset($data['parent_id'])
            ? TaxonomyTerm::query()->findOrFail($data['parent_id'])
            : null;

        $this->mover->handle($term, $newParent);

        return redirect()->route('admin.taxonomy.terms', $taxonomy)
            ->with('status', 'Терминът е преместен.');
    }

    public function destroyTerm(Taxonomy $taxonomy, TaxonomyTerm $term): RedirectResponse
    {
        if ($term->children()->exists()) {
            return back()->with('error', 'Терминът има подтермини. Преместете ги първо.');
        }

        $term->delete();

        return redirect()->route('admin.taxonomy.terms', $taxonomy)
            ->with('status', 'Терминът е изтрит.');
    }
}
