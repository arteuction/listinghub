@extends('layouts.site')

@section('title', 'Любими — '.config('app.name', 'ListingHub'))

@section('content')
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Любими обяви</h1>

    @include('member.partials.flash')

    @if ($listings->isEmpty())
        <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">
            Нямате запазени обяви.
        </p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($listings as $listing)
                <div class="space-y-2">
                    @include('site.partials.listing-card', ['listing' => $listing])
                    <form method="POST" action="{{ route('member.favorites.destroy', $listing) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-slate-600 underline hover:text-slate-900">
                            Премахни от любими
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-8">{{ $listings->links() }}</div>
    @endif
@endsection
