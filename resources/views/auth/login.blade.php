<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name', 'ListingHub') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; display: grid; place-items: center; min-height: 100vh; margin: 0; }
        form { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; width: 320px; }
        h1 { margin: 0 0 1rem; font-size: 1.4rem; }
        label { display: block; margin: .8rem 0 .25rem; font-weight: 600; font-size: .9rem; }
        input { width: 100%; padding: .5rem .6rem; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; }
        button { margin-top: 1.2rem; width: 100%; padding: .6rem; background: #2563eb; color: #fff; border: 0; border-radius: 8px; font-size: 1rem; cursor: pointer; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .6rem .8rem; margin-bottom: 1rem; font-size: .9rem; }
    </style>
</head>
<body>
    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <h1>Sign in</h1>

        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">

        <button type="submit">Sign in</button>
    </form>
</body>
</html>
