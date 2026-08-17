@extends('layouts.site')

@section('title', config('app.name', 'ListingHub').' — национален каталог за България')
@section('canonical', route('home'))

@section('content')
    {{-- CMS Hero block (if admin set one), otherwise default hero --}}
    @php
        $heroBlock = ($homeBlocks ?? collect())->firstWhere('block_type', \App\Enums\ContentBlockType::Hero);
    @endphp

    @if ($heroBlock)
        <section class="mb-12 rounded-xl bg-slate-800 text-white px-6 py-12 text-center">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                {{ $heroBlock->content['title'] ?? '' }}
            </h1>
            @if (!empty($heroBlock->content['subtitle']))
                <p class="mx-auto mt-3 max-w-2xl text-slate-300">{{ $heroBlock->content['subtitle'] }}</p>
            @endif

            <form action="{{ route('listings.index') }}" method="GET" role="search"
                  class="mx-auto mt-6 flex max-w-xl flex-col gap-2 sm:flex-row">
                <label for="hero-search" class="sr-only">Какво търсите?</label>
                <input id="hero-search" type="search" name="q" placeholder="Какво търсите?"
                       class="w-full rounded-md border border-slate-600 bg-slate-700 px-4 py-2.5 text-white placeholder-slate-400 focus:border-white focus:ring-1 focus:ring-white">
                <button type="submit"
                        class="rounded-md bg-white px-6 py-2.5 font-medium text-slate-900 hover:bg-slate-100">
                    Търси
                </button>
            </form>

            @if (!empty($heroBlock->content['cta_text']) && !empty($heroBlock->content['cta_url']))
                <a href="{{ $heroBlock->content['cta_url'] }}"
                   class="mt-4 inline-block text-sm text-slate-300 underline hover:text-white">
                    {{ $heroBlock->content['cta_text'] }} →
                </a>
            @endif
        </section>
    @else
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
    @endif

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
        <section class="mb-12">
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

    {{-- CMS FAQ and other blocks (excluding Hero, already rendered above) --}}
    @foreach (($homeBlocks ?? collect())->reject(fn ($b) => $b->block_type === \App\Enums\ContentBlockType::Hero) as $block)
        @if ($block->block_type === \App\Enums\ContentBlockType::Faq)
            <section class="mb-12 rounded-xl border border-slate-200 bg-white px-6 py-8" aria-label="Често задавани въпроси">
                <h2 class="text-xl font-semibold mb-4">{{ $block->content['title'] ?? 'ЧЗВ' }}</h2>
                @foreach ($block->content['items'] ?? [] as $faq)
                    <details class="border-b border-slate-200 py-3 group">
                        <summary class="font-medium cursor-pointer hover:text-slate-700">{{ $faq['question'] ?? '' }}</summary>
                        <p class="mt-2 text-slate-600">{{ $faq['answer'] ?? '' }}</p>
                    </details>
                @endforeach
            </section>
        @elseif ($block->block_type === \App\Enums\ContentBlockType::Announcement)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-blue-800">
                {{ $block->content['text'] ?? '' }}
            </div>
        @elseif ($block->block_type === \App\Enums\ContentBlockType::RichText)
            <section class="mb-12 rounded-xl border border-slate-200 bg-white px-6 py-8 prose prose-slate max-w-none">
                @tiptap($block->content['tiptap'] ?? [])
            </section>
        @endif
    @endforeach
@endsection
