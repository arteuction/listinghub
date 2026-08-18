<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Content\CreateContentBlock;
use App\Actions\Content\DeleteContentBlock;
use App\Actions\Content\PublishContentBlock;
use App\Actions\Content\ReorderContentBlocks;
use App\Actions\Content\UnpublishContentBlock;
use App\Actions\Content\UpdateContentBlock;
use App\Enums\ContentBlockType;
use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\Page;
use App\Support\Content\BlockSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageContentBlockController extends Controller
{
    public function __construct(
        private readonly CreateContentBlock $creator,
        private readonly UpdateContentBlock $updater,
        private readonly DeleteContentBlock $deleter,
        private readonly ReorderContentBlocks $reorderer,
        private readonly PublishContentBlock $publisher,
        private readonly UnpublishContentBlock $unpublisher,
    ) {}

    /** Abort 404 unless the block actually belongs to the given page. */
    private function ensureOwnership(Page $page, ContentBlock $block): void
    {
        if ($block->owner_type !== $page->getMorphClass() || $block->owner_id !== $page->id) {
            throw new NotFoundHttpException;
        }
    }

    public function create(Page $page): View
    {
        return view('admin.pages.blocks-create', [
            'page' => $page,
            'types' => ContentBlockType::creatableCases(),
        ]);
    }

    public function store(Request $request, Page $page): RedirectResponse
    {
        $request->validate([
            'block_type' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $type = ContentBlockType::from($request->input('block_type'));

        if (! $type->isCreatable()) {
            abort(422, 'Block type "'.$type->value.'" is not yet fully supported (needs schema, admin form, and public renderer).');
        }

        $data = $request->validate(array_merge(
            ['block_type' => ['required', 'string'], 'sort_order' => ['nullable', 'integer', 'min:0']],
            BlockSchema::validationRules($type),
        ));

        $this->creator->handle(
            type: $type,
            content: $data['content'] ?? [],
            owner: $page,
            actor: $request->user(),
            sortOrder: (int) ($data['sort_order'] ?? 1000),
        );

        return redirect()->route('admin.pages.blocks', $page)
            ->with('status', 'Блокът е добавен.');
    }

    public function edit(Page $page, ContentBlock $block): View
    {
        $this->ensureOwnership($page, $block);
        $block->load('revisions');

        return view('admin.pages.blocks-edit', [
            'page' => $page,
            'block' => $block,
        ]);
    }

    public function update(Request $request, Page $page, ContentBlock $block): RedirectResponse
    {
        $this->ensureOwnership($page, $block);

        $data = $request->validate(
            BlockSchema::validationRules($block->block_type),
        );

        $this->updater->handle($block, ['content' => $data['content'] ?? []], $block->version, $request->user());

        return redirect()->route('admin.pages.blocks', $page)
            ->with('status', 'Блокът е обновен.');
    }

    public function destroy(Request $request, Page $page, ContentBlock $block): RedirectResponse
    {
        $this->ensureOwnership($page, $block);
        $this->deleter->handle($block, $request->user());

        return redirect()->route('admin.pages.blocks', $page)
            ->with('status', 'Блокът е изтрит.');
    }

    public function reorder(Request $request, Page $page): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'uuid'],
        ]);

        $uuids = $data['ids'];
        $idMap = ContentBlock::query()
            ->whereIn('uuid', $uuids)
            ->where('owner_type', $page->getMorphClass())
            ->where('owner_id', $page->id)
            ->pluck('id', 'uuid');

        if ($idMap->count() !== count($uuids)) {
            $unknown = array_diff($uuids, $idMap->keys()->all());

            return response()->json([
                'error' => 'Block UUIDs not found in this page: '.implode(', ', $unknown),
            ], 422);
        }

        $orderedIds = array_map(fn (string $uuid): int => $idMap[$uuid], $uuids);

        $this->reorderer->handle($orderedIds, $page);

        return response()->json(['ok' => true]);
    }

    public function publish(Request $request, Page $page, ContentBlock $block): RedirectResponse
    {
        $this->ensureOwnership($page, $block);
        $this->publisher->handle($block, $request->user());

        return back()->with('status', 'Блокът е публикуван.');
    }

    public function unpublish(Request $request, Page $page, ContentBlock $block): RedirectResponse
    {
        $this->ensureOwnership($page, $block);
        $this->unpublisher->handle($block, $request->user());

        return back()->with('status', 'Блокът е върнат в чернова.');
    }
}
