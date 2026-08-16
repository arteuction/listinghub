<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Pages\SyncMenuItems;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function __construct(private readonly SyncMenuItems $syncer) {}

    public function index(): View
    {
        return view('admin.menus.index', [
            'menus' => Menu::query()->with('items')->orderBy('name')->get(),
        ]);
    }

    public function edit(Menu $menu): View
    {
        return view('admin.menus.edit', [
            'menu'  => $menu,
            'items' => $menu->items()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $data = $request->validate([
            'items'                => ['present', 'array'],
            'items.*.label'        => ['required', 'string', 'max:255'],
            'items.*.url'          => ['required', 'string', 'max:512'],
            'items.*.open_in_new_tab' => ['nullable', 'boolean'],
            'items.*.parent_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->syncer->handle($menu, $data['items']);

        return redirect()->route('admin.menus.edit', $menu)
            ->with('status', 'Менюто е обновено.');
    }
}
