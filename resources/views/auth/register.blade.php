@extends('layouts.auth')

@section('title', 'Регистрация')
@section('footer')
    Вече имате профил? <a href="{{ route('login') }}" class="font-medium text-slate-900 underline">Влезте</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium">Име</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                   autocomplete="name" maxlength="120"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium">Имейл</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                   autocomplete="username"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Парола</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   aria-describedby="password-hint"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
            <p id="password-hint" class="mt-1 text-xs text-slate-500">
                Поне 10 знака, с букви и цифри.
            </p>
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Повторете паролата</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <button type="submit"
                class="w-full rounded-md bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">
            Създай профил
        </button>
    </form>
@endsection
