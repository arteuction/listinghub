@extends('admin.layout')

@section('title', 'Меню — '.$menu->name)

@section('content')
    <h1>Меню: {{ $menu->name }}</h1>
    <p><a href="{{ route('admin.menus.index') }}">← Назад</a></p>

    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.menus.update', $menu) }}" id="menu-form">
        @csrf @method('PUT')

        {{-- SortableJS handles visual reorder; on submit the hidden sort_order fields reflect DOM order --}}
        <div id="items-container"
             data-sortable
             style="padding:0;">
            @foreach ($items as $i => $item)
                <div class="menu-item"
                     style="display:flex;align-items:flex-start;gap:.5rem;border:1px solid #cbd5e1;padding:.75rem;margin-bottom:.5rem;background:#fff;">
                    <span data-drag-handle
                          aria-hidden="true"
                          title="Влачи за пренареждане"
                          style="cursor:grab;color:#94a3b8;user-select:none;padding-top:.2rem;">⠿</span>
                    <div style="flex:1;display:flex;gap:.5rem;flex-wrap:wrap;">
                        <div>
                            <label>Етикет<br>
                                <input type="text" name="items[{{ $i }}][label]"
                                       value="{{ old("items.$i.label", $item->label) }}"
                                       required maxlength="255" style="width:180px;">
                            </label>
                        </div>
                        <div>
                            <label>URL<br>
                                <input type="text" name="items[{{ $i }}][url]"
                                       value="{{ old("items.$i.url", $item->url) }}"
                                       required maxlength="512" style="width:280px;">
                            </label>
                        </div>
                        <div>
                            <label>Родителски индекс (0-базиран)<br>
                                <input type="number" name="items[{{ $i }}][parent_index]"
                                       value="{{ old("items.$i.parent_index") }}"
                                       min="0" style="width:80px;" placeholder="—">
                            </label>
                        </div>
                        <div style="align-self:flex-end;">
                            <label>
                                <input type="checkbox" name="items[{{ $i }}][open_in_new_tab]"
                                       value="1" {{ $item->open_in_new_tab ? 'checked' : '' }}>
                                Нов таб
                            </label>
                        </div>
                        <div style="align-self:flex-end;">
                            <button type="button" class="remove-item"
                                    style="color:red;background:none;border:none;cursor:pointer;">✕ Премахни</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p>
            <button type="button" id="add-item">+ Добави елемент</button>
        </p>

        <p>
            <button type="submit">Запази менюто</button>
        </p>
    </form>

    <script>
    (function () {
        const container = document.getElementById('items-container');
        const addBtn    = document.getElementById('add-item');
        let idx = {{ $items->count() }};

        addBtn.addEventListener('click', () => {
            const div = document.createElement('div');
            div.className = 'menu-item';
            div.style.cssText = 'display:flex;align-items:flex-start;gap:.5rem;border:1px solid #cbd5e1;padding:.75rem;margin-bottom:.5rem;background:#fff;';
            div.innerHTML = `
                <span data-drag-handle aria-hidden="true" title="Влачи за пренареждане"
                      style="cursor:grab;color:#94a3b8;user-select:none;padding-top:.2rem;">⠿</span>
                <div style="flex:1;display:flex;gap:.5rem;flex-wrap:wrap;">
                    <div><label>Етикет<br>
                        <input type="text" name="items[${idx}][label]" required maxlength="255" style="width:180px;">
                    </label></div>
                    <div><label>URL<br>
                        <input type="text" name="items[${idx}][url]" required maxlength="512" style="width:280px;">
                    </label></div>
                    <div><label>Родителски индекс<br>
                        <input type="number" name="items[${idx}][parent_index]" min="0" style="width:80px;" placeholder="—">
                    </label></div>
                    <div style="align-self:flex-end;">
                        <label><input type="checkbox" name="items[${idx}][open_in_new_tab]" value="1"> Нов таб</label>
                    </div>
                    <div style="align-self:flex-end;">
                        <button type="button" class="remove-item" style="color:red;background:none;border:none;cursor:pointer;">✕ Премахни</button>
                    </div>
                </div>`;
            container.appendChild(div);
            idx++;
        });

        container.addEventListener('click', e => {
            if (e.target.classList.contains('remove-item')) {
                e.target.closest('.menu-item').remove();
            }
        });

        {{-- Re-index item names on submit so the backend receives a clean 0-based array --}}
        document.getElementById('menu-form').addEventListener('submit', () => {
            container.querySelectorAll('.menu-item').forEach((item, newIdx) => {
                item.querySelectorAll('[name]').forEach(el => {
                    el.name = el.name.replace(/items\[\d+\]/, `items[${newIdx}]`);
                });
            });
        });
    })();
    </script>
@endsection
