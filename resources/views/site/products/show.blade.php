@extends('layouts.site')

@section('title', $product->name.' — '.$listing->title.' — '.config('app.name', 'ListingHub'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 155))
@section('canonical', route('listings.products.show', [$listing->slug, $product->slug]))

@section('content')
    <nav aria-label="Навигация по трасе" class="mb-6 text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('home') }}" class="hover:text-slate-900">Начало</a></li>
            <li aria-hidden="true">/</li>
            <li>
                <a href="{{ route('listings.show', $listing->slug) }}" class="hover:text-slate-900">
                    {{ $listing->title }}
                </a>
            </li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[1fr_20rem]">
        <article>
            <header class="mb-6">
                <h1 class="text-3xl font-semibold tracking-tight">{{ $product->name }}</h1>
                @if ($product->price_minor > 0)
                    <p class="mt-2 text-2xl font-medium">
                        {{ \App\Support\Money::of((int) $product->price_minor, $product->currency)->format() }}
                    </p>
                @endif
            </header>

            @if ($product->media->isNotEmpty())
                <div class="mb-8 grid gap-3 sm:grid-cols-2">
                    @foreach ($product->media as $asset)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($asset->disk)->url($asset->path) }}"
                             alt="{{ $asset->alt_text ?: $product->name }}"
                             loading="lazy"
                             class="w-full rounded-lg border border-slate-200 object-cover">
                    @endforeach
                </div>
            @endif

            @if ($product->description)
                <div class="prose prose-slate max-w-none">
                    {!! nl2br(e($product->description)) !!}
                </div>
            @endif

            @if ($product->attributeValues->isNotEmpty())
                <section class="mt-10">
                    <h2 class="mb-4 text-xl font-semibold">Характеристики</h2>
                    <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
                        @foreach ($product->attributeValues as $row)
                            <div class="flex items-baseline justify-between gap-4 px-4 py-3 text-sm">
                                <dt class="text-slate-500">{{ $row->attribute?->name ?? '—' }}</dt>
                                <dd class="text-right font-medium">{{ $row->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </article>

        <aside class="space-y-6">
            <section class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Предлага се от</h2>
                <p class="font-medium">
                    <a href="{{ route('listings.show', $listing->slug) }}" class="underline hover:text-slate-600">
                        {{ $listing->title }}
                    </a>
                </p>
                @if ($listing->settlement)
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $listing->settlement->name }}@if ($listing->settlement->municipality?->region), обл. {{ $listing->settlement->municipality->region->name }}@endif
                    </p>
                @endif
                @if ($listing->phone)
                    <p class="mt-3 text-sm">
                        <a class="underline" href="tel:{{ $listing->phone }}">{{ $listing->phone }}</a>
                    </p>
                @endif
            </section>
        </aside>
    </div>
@endsection
