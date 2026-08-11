<!DOCTYPE html>
<html lang="bg" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'ListingHub'))</title>
    <meta name="description" content="@yield('meta_description', 'Национален каталог с обяви и фирми в България.')">
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
    @endif
    <link rel="alternate" type="application/xml" href="{{ route('sitemap') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow">
        Към основното съдържание
    </a>

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-4">
            <a href="{{ route('home') }}" class="text-xl font-semibold tracking-tight text-slate-900">
                {{ config('app.name', 'ListingHub') }}
            </a>

            <form action="{{ route('listings.index') }}" method="GET" role="search"
                  class="order-3 flex w-full gap-2 sm:order-none sm:w-auto sm:flex-1">
                <label for="site-search" class="sr-only">Търсене на обяви</label>
                <input id="site-search" type="search" name="q" value="{{ $keyword ?? '' }}"
                       placeholder="Търсене по име или описание…"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                <button type="submit"
                        class="shrink-0 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Търси
                </button>
            </form>

            <nav aria-label="Основна навигация" class="ml-auto flex items-center gap-4 text-sm">
                <a href="{{ route('listings.index') }}" class="text-slate-600 hover:text-slate-900">Всички обяви</a>
                @auth
                    {{-- Members hold no admin permissions; linking them to the
                         panel would only ever produce a 403. --}}
                    @can('manage settings')
                        <a href="{{ route('admin.dashboard') }}" class="text-slate-600 hover:text-slate-900">Панел</a>
                    @endcan
                    <a href="{{ route('profile.edit') }}" class="text-slate-600 hover:text-slate-900">Профил</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-600 hover:text-slate-900">Изход</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">Вход</a>
                    <a href="{{ route('register') }}" class="text-slate-600 hover:text-slate-900">Регистрация</a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 py-8">
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-slate-500">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'ListingHub') }} — национален каталог за България.</p>
        </div>
    </footer>
</body>
</html>
