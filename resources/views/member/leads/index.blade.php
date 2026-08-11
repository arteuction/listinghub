@extends('layouts.site')

@section('title', 'Запитвания — '.$listing->title.' — '.config('app.name', 'ListingHub'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold tracking-tight">Запитвания</h1>
    <p class="mb-6 text-sm text-slate-600">{{ $listing->title }}</p>

    @include('member.partials.flash')

    @if ($leads->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">
            Още няма запитвания по тази обява.
        </p>
    @else
        <ul class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
            @foreach ($leads as $lead)
                <li class="p-4 {{ $lead->read_at === null ? 'bg-amber-50' : '' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">
                                {{ $lead->name }}
                                @if ($lead->read_at === null)
                                    <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Ново</span>
                                @endif
                            </p>
                            <p class="text-sm text-slate-600">
                                <a href="mailto:{{ $lead->email }}" class="underline">{{ $lead->email }}</a>
                                @if ($lead->phone) &middot; {{ $lead->phone }} @endif
                            </p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            {{ $lead->created_at?->format('d.m.Y H:i') }}
                            @if ($lead->read_at === null)
                                <form method="POST" action="{{ route('member.listings.leads.read', [$listing, $lead]) }}" class="mt-1">
                                    @csrf
                                    <button type="submit" class="text-slate-600 underline hover:text-slate-900">
                                        Отбележи като прочетено
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $lead->message }}</p>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $leads->links() }}</div>
    @endif
@endsection
