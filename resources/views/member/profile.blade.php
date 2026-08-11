@extends('layouts.site')

@section('title', 'Моят профил — '.config('app.name', 'ListingHub'))

@section('content')
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Моят профил</h1>

    @if (session('status'))
        <div role="status"
             class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @unless ($user->hasVerifiedEmail())
        <div role="alert"
             class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Имейлът ви не е потвърден.
            <a href="{{ route('verification.notice') }}" class="font-medium underline">Потвърдете го</a>.
        </div>
    @endunless

    <div class="grid gap-8 lg:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="mb-4 text-lg font-semibold">Данни</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-sm font-medium">Име</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                           maxlength="120" autocomplete="name"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                    @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium">Имейл</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                           autocomplete="username"
                           aria-describedby="email-hint"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                    <p id="email-hint" class="mt-1 text-xs text-slate-500">
                        Смяната на адреса изисква ново потвърждение.
                    </p>
                    @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="about" class="block text-sm font-medium">За мен</label>
                    <textarea id="about" name="about" rows="4" maxlength="2000"
                              class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">{{ old('about', $user->about) }}</textarea>
                    @error('about') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Запази
                </button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="mb-4 text-lg font-semibold">Смяна на паролата</h2>

            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="block text-sm font-medium">Текуща парола</label>
                    <input id="current_password" name="current_password" type="password" required
                           autocomplete="current-password"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                    @error('current_password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium">Нова парола</label>
                    <input id="new_password" name="password" type="password" required autocomplete="new-password"
                           aria-describedby="new-password-hint"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                    <p id="new-password-hint" class="mt-1 text-xs text-slate-500">Поне 10 знака, с букви и цифри.</p>
                    @error('password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium">Повторете паролата</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           autocomplete="new-password"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                </div>

                <button type="submit"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Смени паролата
                </button>
            </form>
        </section>
    </div>
@endsection
