@extends('layouts.auth')

@section('title', 'Потвърдете имейла си')
@section('subtitle', 'Изпратихме линк за потвърждение на адреса ви.')

@section('content')
    <div class="space-y-4">
        <p class="text-sm text-slate-600">
            Ако не сте получили писмото, можем да изпратим ново.
        </p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">
                Изпрати отново
            </button>
        </form>

        <div class="flex justify-between text-sm">
            <a href="{{ route('profile.edit') }}" class="text-slate-600 underline hover:text-slate-900">Профил</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-slate-600 underline hover:text-slate-900">Изход</button>
            </form>
        </div>
    </div>
@endsection
