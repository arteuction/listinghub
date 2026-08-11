@extends('layouts.site')

@php
    $dayNames = ['Неделя', 'Понеделник', 'Вторник', 'Сряда', 'Четвъртък', 'Петък', 'Събота'];
@endphp

@section('title', 'Работно време — '.$listing->title.' — '.config('app.name', 'ListingHub'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold tracking-tight">Работно време</h1>
    <p class="mb-6 text-sm text-slate-600">{{ $listing->title }}</p>

    @include('member.partials.flash')

    <form method="POST" action="{{ route('member.listings.hours.update', $listing) }}" class="max-w-2xl">
        @csrf
        @method('PUT')

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <caption class="sr-only">Седмично работно време</caption>
                <thead class="border-b border-slate-200 bg-slate-50 text-left">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Ден</th>
                        <th scope="col" class="px-4 py-3 font-medium">Затворено</th>
                        <th scope="col" class="px-4 py-3 font-medium">Отваря</th>
                        <th scope="col" class="px-4 py-3 font-medium">Затваря</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($dayNames as $dow => $name)
                        @php $hour = $hours[$dow] ?? null; @endphp
                        <tr>
                            <td class="px-4 py-3">
                                {{ $name }}
                                <input type="hidden" name="days[{{ $dow }}][day_of_week]" value="{{ $dow }}">
                            </td>
                            <td class="px-4 py-3">
                                <input type="checkbox" name="days[{{ $dow }}][is_closed]" value="1"
                                       @checked(old("days.{$dow}.is_closed", $hour?->is_closed))
                                       aria-label="{{ $name }} — затворено">
                            </td>
                            <td class="px-4 py-3">
                                <input type="time" name="days[{{ $dow }}][opens_at]"
                                       value="{{ old("days.{$dow}.opens_at", $hour?->opens_at) }}"
                                       class="rounded-md border border-slate-300 px-2 py-1 text-sm"
                                       aria-label="{{ $name }} — отваря">
                            </td>
                            <td class="px-4 py-3">
                                <input type="time" name="days[{{ $dow }}][closes_at]"
                                       value="{{ old("days.{$dow}.closes_at", $hour?->closes_at) }}"
                                       class="rounded-md border border-slate-300 px-2 py-1 text-sm"
                                       aria-label="{{ $name }} — затваря">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="mt-4 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Запази работното време
        </button>
    </form>

    <h2 class="mb-3 mt-10 text-lg font-medium">Изключения по дати</h2>
    <p class="mb-4 text-sm text-slate-600">Например официални празници или еднократна промяна в графика.</p>

    @if ($exceptions->isEmpty())
        <p class="mb-6 text-sm text-slate-500">Няма добавени изключения.</p>
    @else
        <ul class="mb-6 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
            @foreach ($exceptions as $exception)
                <li class="flex items-center justify-between px-4 py-3 text-sm">
                    <span>
                        {{ $exception->date->format('d.m.Y') }} —
                        {{ $exception->is_closed ? 'Затворено' : $exception->opens_at.'–'.$exception->closes_at }}
                        @if ($exception->note) <span class="text-slate-500">({{ $exception->note }})</span> @endif
                    </span>
                    <form method="POST" action="{{ route('member.listings.hours.exceptions.destroy', [$listing, $exception]) }}"
                          onsubmit="return confirm('Да премахна ли изключението?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-700 underline hover:text-red-900">Премахни</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('member.listings.hours.exceptions.store', $listing) }}" class="max-w-md space-y-4">
        @csrf

        <div>
            <label for="date" class="block text-sm font-medium">Дата</label>
            <input id="date" name="date" type="date" value="{{ old('date') }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label><input type="checkbox" name="is_closed" value="1" @checked(old('is_closed', true))> Затворено цял ден</label>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="opens_at" class="block text-sm font-medium">Отваря</label>
                <input id="opens_at" name="opens_at" type="time" value="{{ old('opens_at') }}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="closes_at" class="block text-sm font-medium">Затваря</label>
                <input id="closes_at" name="closes_at" type="time" value="{{ old('closes_at') }}"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label for="note" class="block text-sm font-medium">Бележка (по избор)</label>
            <input id="note" name="note" maxlength="255" value="{{ old('note') }}"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100">
            Добави изключение
        </button>
    </form>
@endsection
