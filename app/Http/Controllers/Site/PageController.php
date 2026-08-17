<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\ContentBlockStatus;
use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PreviewToken;
use App\Models\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    /** Public page — only Published pages are visible. */
    public function show(string $slug): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', PageStatus::Published)
            ->firstOrFail();

        $blocks = $page->contentBlocks()
            ->where('status', ContentBlockStatus::Published)
            ->get();

        $seo = SeoMeta::query()
            ->where('seoable_type', $page->getMorphClass())
            ->where('seoable_id', $page->id)
            ->first();

        return view('site.pages.show', compact('page', 'blocks', 'seo'));
    }

    /** Preview via token — shows Draft page to token holder. */
    public function preview(Request $request): View
    {
        $tokenStr = $request->query('token', '');

        $token = PreviewToken::query()
            ->where('token', $tokenStr)
            ->where('previewable_type', 'page')
            ->first();

        if (! $token || $token->isExpired()) {
            throw new NotFoundHttpException('Preview token is invalid or expired.');
        }

        /** @var Page $page */
        $page = Page::query()->findOrFail($token->previewable_id);

        $blocks = $page->contentBlocks()->get(); // all statuses in preview

        return view('site.pages.show', [
            'page' => $page,
            'blocks' => $blocks,
            'seo' => null,
            'isPreview' => true,
        ]);
    }
}
