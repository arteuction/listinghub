@extends('layouts.auth')

@section('title', 'Вход')
@section('footer')
    Нямате профил? <a href="{{ route('register') }}" class="font-medium text-slate-900 underline">Регистрирайте се</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium">Имейл</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   autocomplete="username"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Парола</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <button type="submit"
                class="w-full rounded-md bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">
            Вход
        </button>

        <p class="text-center text-sm">
            <a href="{{ route('password.request') }}" class="text-slate-600 underline hover:text-slate-900">
                Забравена парола?
            </a>
        </p>
    </form>
@endsection
