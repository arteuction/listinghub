<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Categories\CreateCategory as CreateCategoryAction;
use App\Actions\Categories\DeleteCategory as DeleteCategoryAction;
use App\Actions\Categories\UpdateCategory as UpdateCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\Catalog\PublicListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Category CRUD. Categories are the spine of the catalog — a listing belongs
 * to exactly one, custom fields are defined per category, and browsing a
 * parent shows the whole subtree — so the destructive edges are guarded
 * rather than left to the database to reject with a foreign-key error.
 *
 * Three rules the UI cannot express and the schema does not enforce:
 *
 *  1. A category may not become its own ancestor. The parent select excludes
 *     the subtree, and update() re-checks, because the select is only a
 *     suggestion — the id is posted by the client.
 *  2. A category with children or listings is not deletable. Deleting it
 *     would orphan the children (parent_id points at a gone row) and take the
 *     listings with it under the cascade. Move them first; the error says so.
 *  3. Deactivating is NOT deleting. is_active=false hides a category from the
 *     public catalog while its listings keep their rows, which is what an
 *     operator retiring a category almost always means.
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly PublicListingQuery $catalog,
        private readonly CreateCategoryAction $creator,
        private readonly UpdateCategoryAction $updater,
        private readonly DeleteCategoryAction $deleter,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        return view('admin.categories.index', [
            'categories' => Category::query()
                ->withCount(['children', 'listings'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.form', [
            'category' => null,
            'parents' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $this->creator->handle($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Категорията е създадена.');
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        // The subtree cannot be a parent of its own root, so it is not offered.
        $forbidden = $this->catalog->categorySubtreeIds($category);

        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::query()
                ->whereNotIn('id', $forbidden)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        try {
            $this->updater->handle($category, $request->validated());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('admin.categories.index')->with('status', 'Категорията е обновена.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        try {
            $this->deleter->handle($category);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.categories.index')->with('status', 'Категорията е изтрита.');
    }
}
