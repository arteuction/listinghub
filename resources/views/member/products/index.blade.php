@extends('layouts.site')

@section('title', 'Продукти — '.$listing->title.' — '.config('app.name', 'ListingHub'))

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Продукти</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $listing->title }}</p>
        </div>
        <a href="{{ route('member.listings.products.create', $listing) }}"
           class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Нов продукт
        </a>
    </div>

    @include('member.partials.flash')

    @if ($products->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">
            Още няма добавени продукти.
        </p>
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <caption class="sr-only">Продукти на обявата</caption>
                <thead class="border-b border-slate-200 bg-slate-50 text-left">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Име</th>
                        <th scope="col" class="px-4 py-3 font-medium">Цена</th>
                        <th scope="col" class="px-4 py-3 font-medium">Състояние</th>
                        <th scope="col" class="px-4 py-3 font-medium"><span class="sr-only">Действия</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($products as $product)
                        <tr>
                            <td class="px-4 py-3">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ (new \App\Support\Money($product->price_minor, $product->currency))->format() }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $product->status->value }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('member.listings.products.edit', [$listing, $product]) }}"
                                       class="text-slate-600 underline hover:text-slate-900">Редакция</a>
                                    <form method="POST" action="{{ route('member.listings.products.destroy', [$listing, $product]) }}"
                                          onsubmit="return confirm('Сигурни ли сте, че искате да изтриете продукта?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-700 underline hover:text-red-900">Изтрий</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
