<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Pages\CreatePage;
use App\Actions\Pages\PublishPage;
use App\Actions\Pages\UnpublishPage;
use App\Actions\Pages\UpdatePage;
use App\Actions\Publishing\IssuePreviewToken;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private readonly CreatePage $creator,
        private readonly UpdatePage $updater,
        private readonly PublishPage $publisher,
        private readonly UnpublishPage $unpublisher,
        private readonly IssuePreviewToken $tokenIssuer,
    ) {}

    public function index(): View
    {
        return view('admin.pages.index', [
            'pages' => Page::query()->orderBy('sort_order')->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.form', ['page' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:pages,slug', 'regex:/^[a-z0-9-]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->creator->handle(
            title: $data['title'],
            slug: $data['slug'],
            actor: $request->user(),
            sortOrder: (int) ($data['sort_order'] ?? 1000),
        );

        return redirect()->route('admin.pages.index')->with('status', 'Страницата е създадена.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        if (! $page->is_system) {
            $rules['slug'] = ['required', 'string', 'max:255', 'unique:pages,slug,'.$page->id, 'regex:/^[a-z0-9-]+$/'];
        }

        $data = $request->validate($rules);

        $changes = ['title' => $data['title'], 'sort_order' => (int) ($data['sort_order'] ?? 1000)];
        if (! $page->is_system) {
            $changes['slug'] = $data['slug'];
        }

        $this->updater->handle($page, $changes, $request->user());

        return redirect()->route('admin.pages.index')->with('status', 'Страницата е обновена.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        if ($page->is_system) {
            return back()->with('error', 'Системните страници не могат да се изтриват.');
        }

        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', 'Страницата е изтрита.');
    }

    public function publish(Request $request, Page $page): RedirectResponse
    {
        $this->publisher->handle($page, $request->user());

        return back()->with('status', 'Страницата е публикувана.');
    }

    public function unpublish(Request $request, Page $page): RedirectResponse
    {
        $this->unpublisher->handle($page, $request->user());

        return back()->with('status', 'Страницата е върната в чернова.');
    }

    public function preview(Request $request, Page $page): RedirectResponse
    {
        $token = $this->tokenIssuer->handle($page, $request->user());

        return redirect()->route('pages.preview', ['token' => $token->token]);
    }

    public function blocks(Page $page): View
    {
        return view('admin.pages.blocks', [
            'page' => $page,
            'blocks' => $page->contentBlocks()->withTrashed(false)->get(),
        ]);
    }
}
