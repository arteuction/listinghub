@extends('layouts.auth')

@section('title', 'Забравена парола')
@section('subtitle', 'Ще изпратим линк за смяна на паролата.')
@section('footer')
    <a href="{{ route('login') }}" class="font-medium text-slate-900 underline">Обратно към вход</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium">Имейл</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   autocomplete="username"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
        </div>

        <button type="submit"
                class="w-full rounded-md bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">
            Изпрати линк
        </button>
    </form>
@endsection
