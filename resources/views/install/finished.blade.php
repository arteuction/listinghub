@extends('install.layout')

@section('content')
    <h1>🎉 Installation complete</h1>
    <p>ListingHub is installed. Your administrator account is ready and the
       installer is now locked.</p>
    <p style="opacity:.7;font-size:.9rem;">Reloading the installer will now return
       404 — this is expected. Continue to your site and sign in.</p>
    <a class="btn" href="{{ url('/') }}">Go to site →</a>
@endsection
