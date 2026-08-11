<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomFieldRequest;
use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for custom-field definitions. Thin: every mutation goes through a
 * domain action (never writes the model directly), so I1–I11 are enforced in
 * one place. A CustomFieldConflict becomes a per-field validation error.
 */
class CustomFieldController extends Controller
{
    public function index(Category $category): View
    {
        $fields = $category->customFields()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.custom_fields.index', compact('category', 'fields'));
    }

    public function create(Category $category): View
    {
        return view('admin.custom_fields.form', [
            'category' => $category,
            'field' => null,
            'types' => CustomFieldType::cases(),
        ]);
    }

    public function store(CustomFieldRequest $request, Category $category, CreateCustomField $action): RedirectResponse
    {
        try {
            $action->handle($category, $request->validated());
        } catch (CustomFieldConflict $e) {
            return back()->withInput()->withErrors(['definition' => $e->getMessage()]);
        }

        return redirect()->route('admin.custom-fields.index', $category);
    }

    public function edit(Category $category, CustomField $customField): View
    {
        $this->assertOwned($category, $customField);

        return view('admin.custom_fields.form', [
            'category' => $category,
            'field' => $customField,
            'types' => CustomFieldType::cases(),
        ]);
    }

    public function update(CustomFieldRequest $request, Category $category, CustomField $customField, UpdateCustomField $action): RedirectResponse
    {
        $this->assertOwned($category, $customField);

        try {
            $action->handle($customField, $request->validated());
        } catch (CustomFieldConflict $e) {
            return back()->withInput()->withErrors(['definition' => $e->getMessage()]);
        }

        return redirect()->route('admin.custom-fields.index', $category);
    }

    public function destroy(Category $category, CustomField $customField, DeleteCustomField $action): RedirectResponse
    {
        $this->assertOwned($category, $customField);

        try {
            $action->handle($customField);
        } catch (CustomFieldConflict $e) {
            return back()->withErrors(['definition' => $e->getMessage()]);
        }

        return redirect()->route('admin.custom-fields.index', $category);
    }

    private function assertOwned(Category $category, CustomField $field): void
    {
        abort_unless((int) $field->category_id === (int) $category->id, 404);
    }
}
