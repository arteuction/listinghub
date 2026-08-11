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

        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            {{ $product ? 'Запази' : 'Създай' }}
        </button>
    </form>
@endsection
