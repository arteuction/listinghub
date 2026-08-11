<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install · {{ config('app.name', 'ListingHub') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 640px; margin: 3rem auto; padding: 2rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
        h1 { margin-top: 0; }
        ul { list-style: none; padding: 0; }
        li { display: flex; justify-content: space-between; padding: .5rem 0; border-bottom: 1px solid #f1f5f9; }
        .ok { color: #16a34a; font-weight: 600; }
        .bad { color: #dc2626; font-weight: 600; }
        .btn { display: inline-block; margin-top: 1.5rem; padding: .6rem 1.2rem; background: #2563eb; color: #fff; border: 0; border-radius: 8px; text-decoration: none; font-size: 1rem; cursor: pointer; }
        label { display: block; margin: .8rem 0 .25rem; font-weight: 600; font-size: .9rem; }
        input, select { width: 100%; padding: .5rem .6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="wrap">
        @yield('content')
    </div>
</body>
</html>
