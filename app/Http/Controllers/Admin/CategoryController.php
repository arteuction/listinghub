<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\Catalog\PublicListingQuery;
use Illuminate\Http\RedirectResponse;
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
    public function __construct(private readonly PublicListingQuery $catalog) {}

    public function index(): View
    {
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
        return view('admin.categories.form', [
            'category' => null,
            'parents' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Category::query()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.categories.index')->with('status', 'Категорията е създадена.');
    }

    public function edit(Category $category): View
    {
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
        $data = $request->validated();
        $parentId = $data['parent_id'] ?? null;

        // Re-check server-side: the excluded options in the select are a
        // convenience, and the posted id is client input like any other.
        if ($parentId !== null && in_array((int) $parentId, $this->catalog->categorySubtreeIds($category), true)) {
            return back()->withInput()->withErrors([
                'parent_id' => 'Категорията не може да е подкатегория на себе си или на свой наследник.',
            ]);
        }

        $category->update([
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.categories.index')->with('status', 'Категорията е обновена.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->withErrors([
                'category' => 'Категорията има подкатегории. Преместете ги преди изтриване.',
            ]);
        }

        if ($category->listings()->exists()) {
            return back()->withErrors([
                'category' => 'Категорията съдържа обяви. Преместете ги или деактивирайте категорията.',
            ]);
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Категорията е изтрита.');
    }
}
