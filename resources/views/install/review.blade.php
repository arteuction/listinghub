@extends('install.layout')

@section('content')
    <h1>Review &amp; install</h1>

    @if ($errors->any())
        <div class="alert">
            @foreach ($errors->all() as $error)
                <p class="bad" style="margin:.2rem 0;">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <h3>Application</h3>
    <ul>
        <li><span>Name</span><span>{{ $env['app_name'] }}</span></li>
        <li><span>URL</span><span>{{ $env['app_url'] }}</span></li>
    </ul>

    <h3>Database</h3>
    <ul>
        <li><span>Connection</span><span>{{ $env['db_connection'] }}</span></li>
        <li><span>Host</span><span>{{ $env['db_host'] ?? '—' }}</span></li>
        <li><span>Database</span><span>{{ $env['db_database'] }}</span></li>
    </ul>

    <h3>Administrator</h3>
    <ul>
        <li><span>Name</span><span>{{ $admin['name'] }}</span></li>
        <li><span>Email</span><span>{{ $admin['email'] }}</span></li>
    </ul>

    <p style="opacity:.6;font-size:.85rem;">Passwords are not shown. Installing runs migrations, seeds roles/plans, and creates your admin.</p>

    <form method="POST" action="{{ route('install.install') }}">
        @csrf
        <button class="btn" type="submit">Install now →</button>
    </form>
@endsection
