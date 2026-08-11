@extends('install.layout')

@section('content')
    <h1>Administrator account</h1>
    <p>You choose these credentials — no default admin is ever seeded.</p>

    @if ($errors->any())
        <div class="alert">
            <ul style="margin:0;padding-left:1.2rem;">
                @foreach ($errors->all() as $error)
                    <li class="bad">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('install.admin.store') }}">
        @csrf

        <label>Name</label>
        <input type="text" name="name" value="{{ old('name') }}" required>

        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required>

        <label>Password</label>
        <input type="password" name="password" autocomplete="new-password" required>

        <label>Confirm password</label>
        <input type="password" name="password_confirmation" autocomplete="new-password" required>

        <button class="btn" type="submit">Continue →</button>
    </form>
@endsection
