@extends('install.layout')

@section('content')
    <h1>Welcome to ListingHub</h1>
    <p>This wizard will check your server, configure the environment, create your
       admin account, and set up the database.</p>
    <p><strong>You</strong> choose the admin email and password during setup —
       no default credentials are ever seeded.</p>
    <a class="btn" href="{{ route('install.requirements') }}">Check requirements →</a>
@endsection
