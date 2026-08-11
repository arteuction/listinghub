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
    </style>
</head>
<body>
    <header>
        <strong>{{ config('app.name', 'ListingHub') }} admin</strong>
        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
