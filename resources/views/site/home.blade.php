@extends('layouts.site')

@section('title', config('app.name', 'ListingHub').' — национален каталог за България')
@section('canonical', route('home'))

@section('content')
    <section class="mb-12 rounded-xl border border-slate-200 bg-white px-6 py-10 text-center">
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
            Намерете фирми и услуги в България
        </h1>
        <p class="mx-auto mt-3 max-w-2xl text-slate-600">
            Каталог с обяви от всички области, общини и населени места в страната.
        </p>

        <form action="{{ route('listings.index') }}" method="GET" role="search"
              class="mx-auto mt-6 flex max-w-xl flex-col gap-2 sm:flex-row">
            <label for="hero-search" class="sr-only">Какво търсите?</label>
            <input id="hero-search" type="search" name="q" placeholder="Какво търсите?"
                   class="w-full rounded-md border border-slate-300 px-4 py-2.5 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
            <button type="submit"
                    class="rounded-md bg-slate-900 px-6 py-2.5 font-medium text-white hover:bg-slate-700">
                Търси
            </button>
        </form>
    </section>

    @if ($categories->isNotEmpty())
        <section class="mb-12">
            <h2 class="mb-4 text-xl font-semibold">Категории</h2>
            <ul class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('categories.show', $category->slug) }}"
                           class="block rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium transition hover:border-slate-300 hover:shadow-sm">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($featured->isNotEmpty())
        <section class="mb-12">
            <h2 class="mb-4 text-xl font-semibold">Промотирани обяви</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($featured as $listing)
                    @include('site.partials.listing-card', ['listing' => $listing])
                @endforeach
            </div>
        </section>
    @endif

    @if ($latest->isNotEmpty())
        <section class="mb-12">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="text-xl font-semibold">Най-нови обяви</h2>
                <a href="{{ route('listings.index') }}" class="text-sm text-slate-600 hover:text-slate-900">
                    Виж всички
                </a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($latest as $listing)
                    @include('site.partials.listing-card', ['listing' => $listing])
                @endforeach
            </div>
        </section>
    @endif

    @if ($regions->isNotEmpty())
        <section>
            <h2 class="mb-4 text-xl font-semibold">Разгледайте по област</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach ($regions as $region)
                    <li>
                        <a href="{{ route('regions.show', $region->slug) }}"
                           class="inline-block rounded-full border border-slate-300 bg-white px-3 py-1 text-sm hover:bg-slate-100">
                            {{ $region->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
