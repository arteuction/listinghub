@extends('admin.layout')

@section('title', 'Users')

@section('content')
    <h1>Users</h1>

    @if (session('status'))
        <div role="status">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}">
        <label for="q">Search</label>
        <input id="q" name="q" type="search" value="{{ $term }}" placeholder="name or email">
        <button type="submit">Search</button>
    </form>

    @if ($users->isEmpty())
        <p>No users found.</p>
    @else
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Roles</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->status->value }}</td>
                        <td>{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td><a href="{{ route('admin.users.edit', $user) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $users->links() }}
    @endif
@endsection
