<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Listings\SyncListingCustomFields;
use App\Enums\ListingStatus;
use App\Exceptions\CustomFieldConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListingRequest;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin listing create/edit with custom fields. The custom-field payload is
 * applied EXCLUSIVELY through SyncListingCustomFields (full replacement) — the
 * controller never touches CustomFieldValue directly. Listing creation and the
 * sync run in one transaction, so an invalid field leaves nothing behind, and a
 * CustomFieldConflict becomes a per-field error (`custom_fields.<key>`).
 */
class ListingController extends Controller
{
    public function create(Category $category): View
    {
        return view('admin.listings.form', [
            'listing' => null,
            'category' => $category,
            'fields' => $this->fieldsFor($category),
            'values' => [],
        ]);
    }

    public function store(ListingRequest $request, SyncListingCustomFields $sync): RedirectResponse
    {
        $data = $request->validated();
        $category = Category::query()->findOrFail($data['category_id']);

        try {
            DB::transaction(function () use ($request, $data, $category, $sync): void {
                $listing = Listing::query()->create([
                    'user_id' => $request->user()->id,
                    'category_id' => $category->id,
                    'title' => $data['title'],
                    'slug' => $this->uniqueSlug($data['title']),
                    'status' => ListingStatus::Draft,
                ]);

                $sync->handle($listing, $this->customFieldInput($request->input('custom_fields', [])));
            });
        } catch (CustomFieldConflict $e) {
            return $this->conflictBack($e);
        }

        return redirect()->route('admin.dashboard');
    }

    public function edit(Listing $listing): View
    {
        $category = $listing->category;
        $values = $listing->customFieldValues()->get()
            ->keyBy('custom_field_id');

        return view('admin.listings.form', [
            'listing' => $listing,
            'category' => $category,
            'fields' => $this->fieldsFor($category),
            'values' => $values,
        ]);
    }

    public function update(ListingRequest $request, Listing $listing, SyncListingCustomFields $sync): RedirectResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($request, $data, $listing, $sync): void {
                // A category change is honoured here; the sync then performs a
                // full replacement and purges values from the old category.
                $listing->update([
                    'title' => $data['title'],
                    'category_id' => (int) $data['category_id'],
                ]);

                $sync->handle($listing->fresh(), $this->customFieldInput($request->input('custom_fields', [])));
            });
        } catch (CustomFieldConflict $e) {
            return $this->conflictBack($e);
        }

        return redirect()->route('admin.dashboard');
    }

    /** @return Collection<int, CustomField> */
    private function fieldsFor(Category $category): Collection
    {
        // The HasMany relation is declared without generics, so ->get() widens
        // to Collection<int, Model>; state the element type here.
        /** @var Collection<int, CustomField> $fields */
        $fields = $category->customFields()->orderBy('sort_order')->orderBy('id')->get();

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function customFieldInput(mixed $input): array
    {
        return is_array($input) ? $input : [];
    }

    private function uniqueSlug(string $title): string
    {
        return Str::slug($title).'-'.Str::lower(Str::random(8));
    }

    private function conflictBack(CustomFieldConflict $e): RedirectResponse
    {
        $key = $e->fieldKey !== null ? "custom_fields.{$e->fieldKey}" : 'listing';

        return back()->withInput()->withErrors([$key => $e->getMessage()]);
    }
}
