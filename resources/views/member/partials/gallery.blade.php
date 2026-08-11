@php use App\Support\ImageLimits; @endphp

<section class="rounded-lg border border-slate-200 bg-white p-6">
    <h2 class="mb-1 text-lg font-semibold">Снимки</h2>
    <p class="mb-4 text-sm text-slate-600">
        До {{ ImageLimits::MAX_PER_LISTING }} снимки. JPEG, PNG или WebP, под 5 MB.
        Всяка снимка се преобразува наново при качване.
    </p>

    @if ($listing->media->isNotEmpty())
        <ul class="mb-6 grid gap-4 sm:grid-cols-3">
            @foreach ($listing->media as $index => $asset)
                <li class="space-y-2">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($asset->disk)->url($asset->path) }}"
                         alt="{{ $asset->alt_text ?: $listing->title }}"
                         loading="lazy"
                         class="aspect-[3/2] w-full rounded-md border border-slate-200 object-cover">

                    <div class="flex items-center justify-between gap-2 text-xs">
                        @if ($index === 0)
                            <span class="rounded bg-slate-900 px-2 py-0.5 font-medium text-white">Основна</span>
                        @else
                            <form method="POST"
                                  action="{{ route('member.listings.media.cover', [$listing, $asset]) }}">
                                @csrf
                                <button type="submit" class="text-slate-600 underline hover:text-slate-900">
                                    Направи основна
                                </button>
                            </form>
                        @endif

                        <form method="POST"
                              action="{{ route('member.listings.media.destroy', [$listing, $asset]) }}"
                              onsubmit="return confirm('Да премахна ли снимката?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-700 underline hover:text-red-900">Премахни</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Its own form: uploads are a separate submit from the listing fields, so
         a validation error on one does not discard the other. --}}
    <form method="POST" action="{{ route('member.listings.media.store', $listing) }}"
          enctype="multipart/form-data" class="space-y-3">
        @csrf
        <div>
            <label for="images" class="block text-sm font-medium">Добави снимки</label>
            <input id="images" name="images[]" type="file" multiple
                   accept="image/jpeg,image/png,image/webp"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('images') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            @error('images.*') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">
            Качи
        </button>
    </form>
</section>
