@extends('layouts.site')

@php
    $heading = $category?->name
        ?? $settlement?->name
        ?? $municipality?->name
        ?? $region?->name
        ?? 'Всички обяви';

    $viewMode = in_array(request('view'), ['map', 'list'], true)
        ? request('view')
        : 'list';

    // Params forwarded to the map API to ensure filter parity.
    $mapParams = array_filter([
        'q'            => $keyword,
        'category'     => $category?->slug,
        'region'       => $region?->slug,
        'municipality' => $municipality?->slug,
        'settlement'   => $settlement?->slug,
    ]);
@endphp

@section('title', $heading.' — '.config('app.name', 'ListingHub'))

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $heading }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ trans_choice('{0}Няма намерени обяви|{1}1 обява|[2,*]:count обяви', $listings->total(), ['count' => $listings->total()]) }}
                @if ($keyword)
                    за „{{ $keyword }}"
                @endif
            </p>
        </div>

        {{-- List / Map toggle --}}
        <div class="flex rounded-md border border-slate-300 overflow-hidden text-sm" role="group" aria-label="Изглед">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'bbox' => null]) }}"
               class="px-4 py-2 flex items-center gap-1.5 {{ $viewMode === 'list' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }}"
               aria-current="{{ $viewMode === 'list' ? 'page' : 'false' }}">
                <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Списък
            </a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'map']) }}"
               class="px-4 py-2 flex items-center gap-1.5 border-l border-slate-300 {{ $viewMode === 'map' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }}"
               aria-current="{{ $viewMode === 'map' ? 'page' : 'false' }}">
                <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 16l4.553 2.276A1 1 0 0021 24.382V5.618a1 1 0 00-1.447-.894L15 7m0 13V7"/>
                </svg>
                Карта
            </a>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[16rem_1fr]">
        <aside aria-label="Филтри">
            <form method="GET" action="{{ route('listings.index') }}" class="space-y-5">
                {{-- Preserve view mode across filter submit --}}
                <input type="hidden" name="view" value="{{ $viewMode }}">

                @if ($keyword)
                    <input type="hidden" name="q" value="{{ $keyword }}">
                @endif

                <div>
                    <label for="filter-category" class="block text-sm font-medium">Категория</label>
                    <select id="filter-category" name="category"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Всички категории</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option->slug }}" @selected($category?->slug === $option->slug)>
                                {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-region" class="block text-sm font-medium">Област</label>
                    <select id="filter-region" name="region"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Цяла България</option>
                        @foreach ($regions as $option)
                            <option value="{{ $option->slug }}" @selected($region?->slug === $option->slug)>
                                {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- EKATTE settlement autocomplete --}}
                <div id="lh-settlement-wrap">
                    <label for="lh-settlement-input" class="block text-sm font-medium">Населено място</label>
                    <input
                        id="lh-settlement-input"
                        type="search"
                        placeholder="Търси населено място…"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="lh-settlement-list"
                        aria-expanded="false"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        value="{{ $settlement?->name ?? '' }}"
                    >
                    {{-- Hidden field carries the slug to the server --}}
                    <input type="hidden" id="lh-settlement-slug" name="settlement"
                           value="{{ $settlement?->slug ?? '' }}">
                    <ul id="lh-settlement-list" role="listbox" aria-label="Предложения за населено място"
                        class="hidden mt-1 max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg text-sm z-20 relative">
                    </ul>
                </div>

                @if ($viewMode !== 'map')
                <div>
                    <label for="filter-sort" class="block text-sm font-medium">Подреждане</label>
                    <select id="filter-sort" name="sort"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($sorts as $key => $label)
                            <option value="{{ $key }}" @selected($sortKey === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="flex gap-2">
                    <button type="submit"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        Приложи
                    </button>
                    <a href="{{ request()->fullUrlWithQuery(['category' => null, 'region' => null, 'settlement' => null, 'q' => null]) }}"
                       class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
                        Изчисти
                    </a>
                </div>
            </form>
        </aside>

        <div>
            @if ($viewMode === 'map')
                @include('site.partials.map', ['mapParams' => $mapParams])

                {{--
                    Accessible list fallback — always in the DOM so keyboard /
                    screen-reader users can navigate listings without the map canvas.
                --}}
                <section
                    aria-label="Списъчен изглед на обявите (достъпен резервен вариант)"
                    class="mt-6 sr-only focus-within:not-sr-only"
                >
                    <h2 class="text-lg font-medium mb-3">Списък на обявите</h2>
                    @if ($listings->isEmpty())
                        <p class="text-slate-500">Няма обяви, отговарящи на избраните критерии.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($listings as $listing)
                                <li>
                                    <a href="{{ route('listings.show', $listing) }}"
                                       class="text-blue-700 underline focus:outline focus:outline-2 focus:outline-blue-500">
                                        {{ $listing->title }}
                                    </a>
                                    @if ($listing->settlement)
                                        <span class="text-slate-500 text-sm"> — {{ $listing->settlement->name }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-4">{{ $listings->links() }}</div>
                    @endif
                </section>
            @else
                <section aria-label="Резултати">
                    @if ($listings->isEmpty())
                        <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">
                            Няма обяви, отговарящи на избраните критерии.
                        </p>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($listings as $listing)
                                @include('site.partials.listing-card', ['listing' => $listing])
                            @endforeach
                        </div>

                        <div class="mt-8">
                            {{ $listings->links() }}
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // EKATTE settlement autocomplete.
    const input  = document.getElementById('lh-settlement-input');
    const hidden = document.getElementById('lh-settlement-slug');
    const list   = document.getElementById('lh-settlement-list');
    const api    = '{{ route('api.catalog.settlements') }}';

    if (!input) return;

    let debounce;

    input.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = input.value.trim();
        hidden.value = '';

        if (q.length < 2) {
            closeList();
            return;
        }

        debounce = setTimeout(function () {
            fetch(api + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(renderList)
                .catch(closeList);
        }, 200);
    });

    function renderList(items) {
        list.innerHTML = '';

        if (!items.length) { closeList(); return; }

        items.forEach(function (item) {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', 'false');
            li.tabIndex = 0;
            li.className = 'px-3 py-2 cursor-pointer hover:bg-slate-100 focus:bg-slate-100';
            const context = [item.municipality, item.region].filter(Boolean).join(', ');
            li.innerHTML = '<span class="font-medium">' + escHtml(item.name) + '</span>'
                + (context ? ' <span class="text-slate-500 text-xs">— ' + escHtml(context) + '</span>' : '');

            li.addEventListener('click', function () { select(item); });
            li.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(item); }
                if (e.key === 'Escape') { closeList(); input.focus(); }
            });
            list.appendChild(li);
        });

        list.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
    }

    function select(item) {
        input.value  = item.name;
        hidden.value = item.slug;
        closeList();
        input.focus();
    }

    function closeList() {
        list.classList.add('hidden');
        list.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#lh-settlement-wrap')) closeList();
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeList();
        if (e.key === 'ArrowDown') {
            const first = list.querySelector('[role=option]');
            if (first) { e.preventDefault(); first.focus(); }
        }
    });

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
}());
</script>
@endpush
