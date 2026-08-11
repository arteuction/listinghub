@extends('layouts.site')

@section('title', ($product ? 'Редакция на продукт' : 'Нов продукт').' — '.config('app.name', 'ListingHub'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold tracking-tight">{{ $product ? 'Редакция на продукт' : 'Нов продукт' }}</h1>
    <p class="mb-6 text-sm text-slate-600">{{ $listing->title }}</p>

    @include('member.partials.flash')

    <form method="POST"
          action="{{ $product
              ? route('member.listings.products.update', [$listing, $product])
              : route('member.listings.products.store', $listing) }}"
          class="max-w-xl space-y-5">
        @csrf
        @if ($product) @method('PUT') @endif

        <div>
            <label for="name" class="block text-sm font-medium">Име</label>
            <input id="name" name="name" value="{{ old('name', $product?->name) }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="description" class="block text-sm font-medium">Описание</label>
            <textarea id="description" name="description" rows="4"
                      class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('description', $product?->description) }}</textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="price_minor" class="block text-sm font-medium">Цена (в стотинки)</label>
                <input id="price_minor" name="price_minor" type="number" min="0" step="1"
                       value="{{ old('price_minor', $product?->price_minor ?? 0) }}" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="currency" class="block text-sm font-medium">Валута</label>
                <input id="currency" name="currency" maxlength="3"
                       value="{{ old('currency', $product?->currency ?? config('listinghub.payments.currency', 'USD')) }}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase">
            </div>
        </div>

        <div>
            <label for="status" class="block text-sm font-medium">Състояние</label>
            <select id="status" name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="draft" @selected(old('status', $product?->status?->value) === 'draft')>Чернова</option>
                <option value="published" @selected(old('status', $product?->status?->value) === 'published')>Публикуван</option>
            </select>
        </div>

        <div>
            <label for="sort_order" class="block text-sm font-medium">Ред на показване</label>
            <input id="sort_order" name="sort_order" type="number" min="0"
                   value="{{ old('sort_order', $product?->sort_order ?? 0) }}"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>


        {{-- Характеристики (3.5.3): пълна замяна при запис; редът се пази. --}}
        <fieldset>
            <legend class="block text-sm font-medium">Характеристики</legend>
            <p class="mb-2 text-xs text-slate-500">Празните редове се пропускат.</p>
            @php
                $existingAttrs = old('attributes',
                    $product?->attributeValues
                        ->map(fn ($row) => ['name' => $row->attribute?->name, 'value' => $row->value])
                        ->all() ?? []);
            @endphp
            <div class="space-y-2">
                @for ($i = 0; $i < max(count($existingAttrs) + 2, 4); $i++)
                    <div class="grid grid-cols-2 gap-2">
                        <input name="attributes[{{ $i }}][name]" placeholder="Име (напр. Цвят)"
                               value="{{ $existingAttrs[$i]['name'] ?? '' }}" maxlength="100"
                               class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <input name="attributes[{{ $i }}][value]" placeholder="Стойност (напр. Червен)"
                               value="{{ $existingAttrs[$i]['value'] ?? '' }}" maxlength="255"
                               class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                @endfor
            </div>
        </fieldset>

        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            {{ $product ? 'Запази' : 'Създай' }}
        </button>
    </form>

    @if ($product)
        {{-- Галерия на продукта (3.5.3) — същият проверен media pipeline. --}}
        <section class="mt-8 max-w-xl">
            <h2 class="mb-3 text-lg font-medium">Галерия</h2>

            @if ($product->media->isNotEmpty())
                <ul class="mb-4 grid grid-cols-3 gap-3">
                    @foreach ($product->media as $asset)
                        <li>
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($asset->disk)->url($asset->path) }}"
                                 alt="{{ $asset->alt_text ?: $product->name }}"
                                 class="aspect-square w-full rounded-md border border-slate-200 object-cover">
                            <form method="POST"
                                  action="{{ route('member.listings.products.media.destroy', [$listing, $product, $asset]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="mt-1 text-xs text-red-700 underline">Премахни</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" enctype="multipart/form-data"
                  action="{{ route('member.listings.products.media.store', [$listing, $product]) }}"
                  class="space-y-2">
                @csrf
                <label for="product-images" class="block text-sm font-medium">Добави изображения</label>
                <input id="product-images" name="images[]" type="file" multiple
                       accept="image/jpeg,image/png,image/webp" class="w-full text-sm">
                <button type="submit"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">Качи</button>
            </form>
        </section>
    @endif
@endsection
