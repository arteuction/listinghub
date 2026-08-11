@extends('layouts.site')

@section('title', $listing->title.' — '.config('app.name', 'ListingHub'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $listing->description), 155))
@section('canonical', route('listings.show', $listing->slug))

@section('content')
    @php
        $place = $listing->settlement;
        $region = $place?->municipality?->region;
    @endphp

    <nav aria-label="Навигация по трасе" class="mb-6 text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('home') }}" class="hover:text-slate-900">Начало</a></li>
            @if ($listing->category)
                <li aria-hidden="true">/</li>
                <li>
                    <a href="{{ route('categories.show', $listing->category->slug) }}" class="hover:text-slate-900">
                        {{ $listing->category->name }}
                    </a>
                </li>
            @endif
            @if ($region)
                <li aria-hidden="true">/</li>
                <li>
                    <a href="{{ route('regions.show', $region->slug) }}" class="hover:text-slate-900">
                        обл. {{ $region->name }}
                    </a>
                </li>
            @endif
        </ol>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[1fr_20rem]">
        <article>
            <header class="mb-6">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl font-semibold tracking-tight">{{ $listing->title }}</h1>
                    @if ($listing->is_featured)
                        <span class="rounded bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Промотирана</span>
                    @endif
                </div>

                @if ($listing->rating_count > 0)
                    <p class="mt-2 text-sm text-slate-600">
                        <span aria-hidden="true">★</span>
                        {{ number_format((float) $listing->rating_avg, 1) }} от 5
                        <span class="text-slate-400">({{ $listing->rating_count }} оценки)</span>
                    </p>
                @endif
            </header>

            @if ($listing->media->isNotEmpty())
                <div class="mb-8 grid gap-3 sm:grid-cols-2">
                    @foreach ($listing->media as $asset)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($asset->disk)->url($asset->path) }}"
                             alt="{{ $asset->alt_text ?: $listing->title }}"
                             loading="lazy"
                             class="w-full rounded-lg border border-slate-200 object-cover">
                    @endforeach
                </div>
            @endif

            @if ($listing->description)
                <div class="prose prose-slate max-w-none">
                    {!! nl2br(e($listing->description)) !!}
                </div>
            @endif

            @if ($listing->products->isNotEmpty())
                <section class="mt-10">
                    <h2 class="mb-4 text-xl font-semibold">Продукти и услуги</h2>
                    <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
                        @foreach ($listing->products as $product)
                            <li class="flex items-baseline justify-between gap-4 px-4 py-3">
                                <span>{{ $product->name }}</span>
                                @if ($product->price_minor > 0)
                                    <span class="shrink-0 font-medium">
                                        {{ \App\Support\Money::of((int) $product->price_minor, $product->currency)->format() }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </article>

        <aside class="space-y-6">
            @auth
                @php $isFavorite = auth()->user()->favorites()->whereKey($listing->getKey())->exists(); @endphp
                <form method="POST"
                      action="{{ $isFavorite ? route('member.favorites.destroy', $listing) : route('member.favorites.store', $listing) }}">
                    @csrf
                    @if ($isFavorite) @method('DELETE') @endif
                    <button type="submit"
                            class="w-full rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-100">
                        {{ $isFavorite ? 'Премахни от любими' : 'Запази в любими' }}
                    </button>
                </form>
            @endauth

            <section class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Контакти</h2>
                <dl class="space-y-2 text-sm">
                    @if ($place)
                        <div>
                            <dt class="text-slate-500">Населено място</dt>
                            <dd>{{ $place->name }}@if ($region), обл. {{ $region->name }}@endif</dd>
                        </div>
                    @endif
                    @if ($listing->address)
                        <div>
                            <dt class="text-slate-500">Адрес</dt>
                            <dd>{{ $listing->address }}</dd>
                        </div>
                    @endif
                    @if ($listing->phone)
                        <div>
                            <dt class="text-slate-500">Телефон</dt>
                            <dd><a class="text-slate-900 underline" href="tel:{{ $listing->phone }}">{{ $listing->phone }}</a></dd>
                        </div>
                    @endif
                    @if ($listing->email)
                        <div>
                            <dt class="text-slate-500">Имейл</dt>
                            <dd><a class="text-slate-900 underline" href="mailto:{{ $listing->email }}">{{ $listing->email }}</a></dd>
                        </div>
                    @endif
                    @if ($listing->website)
                        <div>
                            <dt class="text-slate-500">Уебсайт</dt>
                            <dd>
                                <a class="text-slate-900 underline" href="{{ $listing->website }}"
                                   rel="nofollow noopener" target="_blank">{{ $listing->website }}</a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            @if ($listing->hours->isNotEmpty())
                <section class="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Работно време</h2>
                    @php
                        // day_of_week is stored 0 = Sunday .. 6 = Saturday.
                        $dayNames = ['Неделя', 'Понеделник', 'Вторник', 'Сряда', 'Четвъртък', 'Петък', 'Събота'];
                    @endphp
                    <ul class="space-y-1 text-sm">
                        @foreach ($listing->hours as $hour)
                            <li class="flex justify-between gap-4">
                                <span class="text-slate-600">{{ $dayNames[$hour->day_of_week] ?? '—' }}</span>
                                <span>
                                    @if ($hour->is_closed)
                                        Затворено
                                    @else
                                        {{ $hour->opens_at }}–{{ $hour->closes_at }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-16">
            <h2 class="mb-4 text-xl font-semibold">Подобни обяви</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($related as $relatedListing)
                    @include('site.partials.listing-card', ['listing' => $relatedListing])
                @endforeach
            </div>
        </section>
    @endif
@endsection
