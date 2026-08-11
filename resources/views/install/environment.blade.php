@extends('install.layout')

@section('content')
    <h1>Environment &amp; database</h1>

    @if ($errors->any())
        <div class="alert">
            <ul style="margin:0;padding-left:1.2rem;">
                @foreach ($errors->all() as $error)
                    <li class="bad">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('install.environment.store') }}">
        @csrf

        <label>Application name</label>
        <input type="text" name="app_name" value="{{ old('app_name', 'ListingHub') }}" required>

        <label>Application URL</label>
        <input type="url" name="app_url" value="{{ old('app_url', 'http://localhost') }}" required>

        <label>Database connection</label>
        <select name="db_connection">
            <option value="mysql" @selected(old('db_connection', 'mysql') === 'mysql')>MySQL / MariaDB</option>
            <option value="pgsql" @selected(old('db_connection') === 'pgsql')>PostgreSQL</option>
            <option value="sqlite" @selected(old('db_connection') === 'sqlite')>SQLite</option>
        </select>

        <label>Database host</label>
        <input type="text" name="db_host" value="{{ old('db_host', '127.0.0.1') }}">

        <label>Database port</label>
        <input type="text" name="db_port" value="{{ old('db_port', '3306') }}">

        <label>Database name</label>
        <input type="text" name="db_database" value="{{ old('db_database') }}" required>

        <label>Database username</label>
        <input type="text" name="db_username" value="{{ old('db_username') }}">

        <label>Database password</label>
        <input type="password" name="db_password" value="" autocomplete="new-password">

        <p style="opacity:.6;font-size:.85rem;">The database must be empty. The connection is tested before anything is written.</p>

        <button class="btn" type="submit">Test &amp; continue →</button>
    </form>
@endsection
