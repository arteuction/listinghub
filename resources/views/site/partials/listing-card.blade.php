@php
    /** @var \App\Models\Listing $listing */
    $cover = $listing->media->first();
    $place = $listing->settlement;
@endphp

<article class="relative flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white transition hover:border-slate-300 hover:shadow-sm">
    <div class="aspect-[3/2] w-full bg-slate-100">
        @if ($cover)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk($cover->disk)->url($cover->path) }}"
                 alt="{{ $cover->alt_text ?: $listing->title }}"
                 loading="lazy"
                 class="h-full w-full object-cover">
        @else
            <div class="flex h-full w-full items-center justify-center text-slate-400" aria-hidden="true">
                <span class="text-3xl">🏢</span>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex items-start justify-between gap-2">
            <h3 class="text-base font-semibold leading-snug">
                <a href="{{ route('listings.show', $listing->slug) }}"
                   class="after:absolute after:inset-0 hover:underline">
                    {{ $listing->title }}
                </a>
            </h3>
            @if ($listing->is_featured)
                <span class="shrink-0 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    Промотирана
                </span>
            @endif
        </div>

        @if ($listing->category)
            <p class="text-xs text-slate-500">{{ $listing->category->name }}</p>
        @endif

        @if ($place)
            <p class="text-sm text-slate-600">
                {{ $place->name }}@if ($place->municipality?->region), обл. {{ $place->municipality->region->name }}@endif
            </p>
        @endif

        @if ($listing->rating_count > 0)
            <p class="mt-auto text-sm text-slate-600">
                <span aria-hidden="true">★</span>
                {{ number_format((float) $listing->rating_avg, 1) }}
                <span class="text-slate-400">({{ $listing->rating_count }})</span>
            </p>
        @endif
    </div>
</article>
