@extends('layouts.site')

@section('title', 'Моите обяви — '.config('app.name', 'ListingHub'))

@php use App\Enums\ListingStatus; @endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">Моите обяви</h1>
        <a href="{{ route('member.listings.create') }}"
           class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Нова обява
        </a>
    </div>

    @include('member.partials.flash')

    @if ($listings->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">
            Още нямате обяви.
        </p>
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <caption class="sr-only">Списък с вашите обяви</caption>
                <thead class="border-b border-slate-200 bg-slate-50 text-left">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Заглавие</th>
                        <th scope="col" class="px-4 py-3 font-medium">Категория</th>
                        <th scope="col" class="px-4 py-3 font-medium">Място</th>
                        <th scope="col" class="px-4 py-3 font-medium">Състояние</th>
                        <th scope="col" class="px-4 py-3 font-medium"><span class="sr-only">Действия</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($listings as $listing)
                        @php
                            $badge = match ($listing->status) {
                                ListingStatus::Published => 'bg-emerald-100 text-emerald-800',
                                ListingStatus::Pending => 'bg-amber-100 text-amber-800',
                                ListingStatus::Suspended => 'bg-red-100 text-red-800',
                                default => 'bg-slate-100 text-slate-700',
                            };
                            $label = match ($listing->status) {
                                ListingStatus::Published => 'Публикувана',
                                ListingStatus::Pending => 'За одобрение',
                                ListingStatus::Suspended => 'Спряна',
                                default => 'Чернова',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                @if ($listing->status === ListingStatus::Published)
                                    <a href="{{ route('listings.show', $listing->slug) }}" class="underline">
                                        {{ $listing->title }}
                                    </a>
                                @else
                                    {{ $listing->title }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $listing->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $listing->settlement?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $label }}</span>
                                @if ($listing->status === ListingStatus::Draft && $listing->moderation_note)
                                    <p class="mt-1 max-w-xs text-xs text-amber-800">
                                        Върната за корекции: {{ $listing->moderation_note }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('member.listings.edit', $listing) }}"
                                       class="text-slate-600 underline hover:text-slate-900">Редакция</a>

                                    @if ($listing->status === ListingStatus::Draft)
                                        <form method="POST" action="{{ route('member.listings.submit', $listing) }}">
                                            @csrf
                                            <button type="submit" class="text-slate-600 underline hover:text-slate-900">
                                                Публикувай
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('member.listings.destroy', $listing) }}"
                                          onsubmit="return confirm('Сигурни ли сте, че искате да изтриете обявата?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-700 underline hover:text-red-900">
                                            Изтрий
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $listings->links() }}</div>
    @endif
@endsection
