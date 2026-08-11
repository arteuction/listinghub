@extends('admin.layout')

@section('title', $product ? 'Edit product' : 'New product')

@section('content')
    <h1>{{ $product ? 'Edit' : 'New' }} product — {{ $listing->title }}</h1>

    @if ($errors->any())
        <div class="alert" role="alert">
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $product
              ? route('admin.listings.products.update', [$listing, $product])
              : route('admin.listings.products.store', $listing) }}">
        @csrf
        @if ($product) @method('PUT') @endif

        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name', $product?->name) }}" required>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4">{{ old('description', $product?->description) }}</textarea>

        <label for="price_minor">Price (minor units)</label>
        <input id="price_minor" name="price_minor" type="number" min="0" step="1"
               value="{{ old('price_minor', $product?->price_minor ?? 0) }}" required>

        <label for="currency">Currency</label>
        <input id="currency" name="currency" maxlength="3"
               value="{{ old('currency', $product?->currency ?? config('listinghub.payments.currency', 'USD')) }}">

        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="draft" @selected(old('status', $product?->status?->value) === 'draft')>Draft</option>
            <option value="published" @selected(old('status', $product?->status?->value) === 'published')>Published</option>
            <option value="suspended" @selected(old('status', $product?->status?->value) === 'suspended')>Suspended</option>
        </select>

        <label for="sort_order">Sort order</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $product?->sort_order ?? 0) }}">

        <button type="submit" class="btn">{{ $product ? 'Save' : 'Create' }}</button>
    </form>
@endsection
