@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>
    <p>Welcome, {{ auth()->user()->name }}.</p>

    <ul>
        <li><a href="{{ route('admin.moderation.index') }}">Moderation queue</a></li>
        <li><a href="{{ route('admin.users.index') }}">Users</a></li>
    </ul>
@endsection
