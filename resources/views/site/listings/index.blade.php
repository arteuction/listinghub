@extends('layouts.site')

@php
    $heading = $category?->name
        ?? $settlement?->name
        ?? $municipality?->name
        ?? $region?->name
        ?? 'Всички обяви';
@endphp

@section('title', $heading.' — '.config('app.name', 'ListingHub'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $heading }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ trans_choice('{0}Няма намерени обяви|{1}1 обява|[2,*]:count обяви', $listings->total(), ['count' => $listings->total()]) }}
            @if ($keyword)
                за „{{ $keyword }}”
            @endif
        </p>
    </div>

    <div class="grid gap-8 lg:grid-cols-[16rem_1fr]">
        <aside aria-label="Филтри">
            <form method="GET" action="{{ route('listings.index') }}" class="space-y-5">
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

                <div>
                    <label for="filter-sort" class="block text-sm font-medium">Подреждане</label>
                    <select id="filter-sort" name="sort"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($sorts as $key => $label)
                            <option value="{{ $key }}" @selected($sortKey === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        Приложи
                    </button>
                    <a href="{{ route('listings.index') }}"
                       class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
                        Изчисти
                    </a>
                </div>
            </form>
        </aside>

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
    </div>
@endsection
