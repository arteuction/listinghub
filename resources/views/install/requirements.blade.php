@extends('install.layout')

@section('content')
    <h1>Server requirements</h1>

    <h3>PHP version</h3>
    <ul>
        <li>
            <span>PHP {{ $php['required'] }}+ (found {{ $php['current'] }})</span>
            <span class="{{ $php['passed'] ? 'ok' : 'bad' }}">{{ $php['passed'] ? 'OK' : 'FAIL' }}</span>
        </li>
    </ul>

    <h3>Extensions</h3>
    <ul>
        @foreach ($extensions as $name => $loaded)
            <li>
                <span>ext-{{ $name }}</span>
                <span class="{{ $loaded ? 'ok' : 'bad' }}">{{ $loaded ? 'OK' : 'MISSING' }}</span>
            </li>
        @endforeach
    </ul>

    <h3>Required functions</h3>
    <ul>
        @foreach ($functions as $name => $available)
            <li>
                <span>{{ $name }}()</span>
                <span class="{{ $available ? 'ok' : 'bad' }}">{{ $available ? 'OK' : 'MISSING' }}</span>
            </li>
        @endforeach
    </ul>

    <h3>Writable paths</h3>
    <ul>
        @foreach ($writable as $path => $ok)
            <li>
                <span>{{ $path }}/</span>
                <span class="{{ $ok ? 'ok' : 'bad' }}">{{ $ok ? 'writable' : 'NOT writable' }}</span>
            </li>
        @endforeach
    </ul>

    @if ($satisfied)
        <a class="btn" href="{{ route('install.environment') }}">Continue →</a>
    @else
        <p class="bad">Resolve the failing items above, then reload this page.</p>
    @endif
@endsection
