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

    @if (session('status'))
        <div role="status" class="mt-10 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mt-10 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ------------------------------------------------------------ reviews --}}
    <section class="mt-16" aria-labelledby="reviews-heading">
        <h2 id="reviews-heading" class="mb-4 text-xl font-semibold">
            Отзиви
            @if ($listing->rating_count > 0)
                <span class="text-base font-normal text-slate-500">
                    ({{ number_format((float) $listing->rating_avg, 1) }} от 5 — {{ $listing->rating_count }})
                </span>
            @endif
        </h2>

        @if ($listing->reviews->isEmpty())
            <p class="text-sm text-slate-500">Още няма отзиви за тази обява.</p>
        @else
            <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
                @foreach ($listing->reviews as $review)
                    <li class="p-4">
                        <p class="text-sm font-medium">
                            {{ $review->user?->name ?? 'Потребител' }}
                            <span class="ml-2 text-slate-500" aria-label="Оценка {{ $review->rating }} от 5">
                                {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                            </span>
                        </p>
                        @if ($review->body)
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $review->body }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @auth
            {{-- An owner cannot review their own listing (also enforced server-side). --}}
            @if ((int) $listing->user_id !== (int) auth()->id())
                <form method="POST" action="{{ route('listings.reviews.store', $listing->slug) }}"
                      class="mt-6 max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-4">
                    @csrf
                    <h3 class="font-medium">Оставете отзив</h3>

                    <div>
                        <label for="rating" class="block text-sm font-medium">Оценка</label>
                        <select id="rating" name="rating" required
                                class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @for ($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" @selected(old('rating') == $i)>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label for="body" class="block text-sm font-medium">Коментар (по избор)</label>
                        <textarea id="body" name="body" rows="4"
                                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('body') }}</textarea>
                    </div>

                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        Изпрати отзив
                    </button>
                </form>
            @endif
        @else
            <p class="mt-6 text-sm text-slate-500">
                <a href="{{ route('login') }}" class="underline">Влезте</a>, за да оставите отзив.
            </p>
        @endauth
    </section>

    {{-- ------------------------------------------------------------ contact --}}
    <section class="mt-16" aria-labelledby="contact-heading">
        <h2 id="contact-heading" class="mb-4 text-xl font-semibold">Свържете се</h2>

        <form method="POST" action="{{ route('listings.leads.store', $listing->slug) }}"
              class="max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-4">
            @csrf

            <div>
                <label for="lead-name" class="block text-sm font-medium">Име</label>
                <input id="lead-name" name="name" value="{{ old('name') }}" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="lead-email" class="block text-sm font-medium">Имейл</label>
                    <input id="lead-email" name="email" type="email" value="{{ old('email') }}" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="lead-phone" class="block text-sm font-medium">Телефон (по избор)</label>
                    <input id="lead-phone" name="phone" value="{{ old('phone') }}"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label for="lead-message" class="block text-sm font-medium">Съобщение</label>
                <textarea id="lead-message" name="message" rows="4" required
                          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('message') }}</textarea>
            </div>

            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Изпрати
            </button>
        </form>
    </section>

    {{-- -------------------------------------------------------------- claim --}}
    @auth
        @if ((int) $listing->user_id !== (int) auth()->id())
            <section class="mt-16" aria-labelledby="claim-heading">
                <h2 id="claim-heading" class="mb-2 text-xl font-semibold">Това е моят бизнес</h2>
                <p class="mb-4 text-sm text-slate-600">
                    Изпратете заявка за поемане на обявата. Заявката се разглежда от администратор.
                </p>

                <form method="POST" action="{{ route('listings.claims.store', $listing->slug) }}"
                      class="max-w-lg space-y-4 rounded-lg border border-slate-200 bg-white p-4">
                    @csrf
                    <div>
                        <label for="claim-message" class="block text-sm font-medium">Как можем да потвърдим, че обявата е ваша?</label>
                        <textarea id="claim-message" name="message" rows="4" required
                                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('message') }}</textarea>
                    </div>

                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
                        Изпрати заявка
                    </button>
                </form>
            </section>
        @endif
    @endauth

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
