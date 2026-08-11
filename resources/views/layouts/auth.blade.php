<!DOCTYPE html>
<html lang="bg" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ config('app.name', 'ListingHub') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-slate-50 px-4 py-12 text-slate-900 antialiased">
    <div class="w-full max-w-md">
        <a href="{{ route('home') }}" class="mb-6 block text-center text-xl font-semibold tracking-tight">
            {{ config('app.name', 'ListingHub') }}
        </a>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="mb-1 text-lg font-semibold">@yield('title')</h1>
            @hasSection('subtitle')
                <p class="mb-4 text-sm text-slate-600">@yield('subtitle')</p>
            @else
                <div class="mb-4"></div>
            @endif

            @if (session('status'))
                <div role="status"
                     class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert"
                     class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>

        @hasSection('footer')
            <p class="mt-4 text-center text-sm text-slate-600">@yield('footer')</p>
        @endif
    </div>
</body>
</html>
