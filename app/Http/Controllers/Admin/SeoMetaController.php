<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Publishing\UpsertSeoMeta;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\TaxonomyTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeoMetaController extends Controller
{
    private const ROBOTS_OPTIONS = [
        'index,follow',
        'noindex,nofollow',
        'noindex,follow',
        'index,nofollow',
    ];

    public function __construct(private readonly UpsertSeoMeta $upserter) {}

    public function editForPage(Page $page): View
    {
        return $this->editView($page, route('admin.pages.seo.update', $page));
    }

    public function updateForPage(Request $request, Page $page): RedirectResponse
    {
        $this->upserter->handle($page, $this->validated($request));

        return redirect()->route('admin.pages.edit', $page)->with('status', 'SEO е записано.');
    }

    public function editForTerm(TaxonomyTerm $term): View
    {
        return $this->editView($term, route('admin.taxonomy.terms.seo.update', $term));
    }

    public function updateForTerm(Request $request, TaxonomyTerm $term): RedirectResponse
    {
        $this->upserter->handle($term, $this->validated($request));

        return back()->with('status', 'SEO е записано.');
    }

    private function editView(Model $resource, string $formAction): View
    {
        $existing = SeoMeta::query()
            ->where('seoable_type', $resource->getMorphClass())
            ->where('seoable_id', $resource->getKey())
            ->first();

        return view('admin.seo.edit', [
            'resource'      => $resource,
            'seo'           => $existing,
            'robotsOptions' => self::ROBOTS_OPTIONS,
            'formAction'    => $formAction,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'meta_title'       => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'robots'           => ['nullable', 'string', 'in:'.implode(',', self::ROBOTS_OPTIONS)],
            'canonical_path'   => ['nullable', 'string', 'max:512', 'regex:/^\//'],
            'og'               => ['nullable', 'array'],
            'og.title'         => ['nullable', 'string', 'max:120'],
            'og.description'   => ['nullable', 'string', 'max:300'],
            'og.image_path'    => ['nullable', 'string', 'max:512'],
        ]);
    }
}
