<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · {{ config('app.name', 'ListingHub') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #0f172a; background: #f1f5f9; }
        header { background: #0f172a; color: #e2e8f0; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        header a, header button { color: #e2e8f0; background: none; border: 0; cursor: pointer; font: inherit; text-decoration: none; }
        main { max-width: 960px; margin: 2rem auto; padding: 0 1.5rem; }
        nav.admin { background: #1e293b; padding: .5rem 1.5rem; }
        nav.admin ul { list-style: none; display: flex; flex-wrap: wrap; gap: 1rem; margin: 0; padding: 0; }
        nav.admin a { color: #e2e8f0; text-decoration: none; }
        nav.admin a[aria-current="page"] { text-decoration: underline; font-weight: 600; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: .4rem .6rem; border-bottom: 1px solid #cbd5e1; vertical-align: top; }
        [role="status"] { background: #dcfce7; padding: .6rem 1rem; margin: 1rem 0; }
        [role="alert"] { background: #fee2e2; padding: .6rem 1rem; margin: 1rem 0; }
    </style>
</head>
<body>
    <header>
        <strong>{{ config('app.name', 'ListingHub') }} admin</strong>
        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <button type="submit">Изход</button>
        </form>
    </header>

    {{-- One nav for the whole panel. aria-current marks the active section so
         the current page is announced, not only underlined. --}}
    <nav class="admin" aria-label="Административно меню">
        <ul>
            @foreach ([
                ['admin.dashboard', 'Табло', 'admin.dashboard'],
                ['admin.moderation.index', 'Модерация', 'admin.moderation.*'],
                ['admin.listings.index', 'Обяви', 'admin.listings.*'],
                ['admin.categories.index', 'Категории', 'admin.categories.*'],
                ['admin.pages.index', 'Страници', 'admin.pages.*'],
                ['admin.menus.index', 'Менюта', 'admin.menus.*'],
                ['admin.taxonomy.index', 'Taxonomy', 'admin.taxonomy.*'],
                ['admin.leads.index', 'Запитвания', 'admin.leads.*'],
                ['admin.users.index', 'Потребители', 'admin.users.*'],
                ['admin.settings.edit', 'Настройки', 'admin.settings.*'],
            ] as [$route, $label, $pattern])
                <li>
                    <a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
    <main>
        @yield('content')
    </main>
</body>
</html>
