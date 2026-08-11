@extends('admin.layout')

@section('title', 'Edit user')

@section('content')
    <h1>Edit user — {{ $user->email }}</h1>

    @if ($errors->any())
        <div class="alert" role="alert">
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf
        @method('PUT')

        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" required>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>

        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="active" @selected(old('status', $user->status->value) === 'active')>Active</option>
            <option value="suspended" @selected(old('status', $user->status->value) === 'suspended')>Suspended</option>
        </select>

        <fieldset>
            <legend>Roles</legend>
            @php $current = old('roles', $user->roles->pluck('name')->all()); @endphp
            @foreach ($roles as $role)
                <label>
                    <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                           @checked(in_array($role->name, (array) $current, true))>
                    {{ $role->name }}
                </label>
            @endforeach
        </fieldset>

        <p><small>Passwords are not settable here — the account holder resets their own via the password-reset flow.</small></p>

        <button type="submit" class="btn">Save</button>
    </form>
@endsection
