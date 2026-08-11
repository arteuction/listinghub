@extends('layouts.auth')

@section('title', 'Нова парола')

@section('content')
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm font-medium">Имейл</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required
                   autocomplete="username"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Нова парола</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Повторете паролата</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <button type="submit"
                class="w-full rounded-md bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">
            Смени паролата
        </button>
    </form>
@endsection
