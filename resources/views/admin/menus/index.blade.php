@extends('admin.layout')

@section('title', 'Менюта')

@section('content')
    <h1>Менюта</h1>

    @include('admin.partials.flash')

    <table>
        <thead>
            <tr>
                <th>Наименование</th>
                <th>Handle</th>
                <th>Елементи</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($menus as $menu)
                <tr>
                    <td>{{ $menu->name }}</td>
                    <td><code>{{ $menu->handle }}</code></td>
                    <td>{{ $menu->items->count() }}</td>
                    <td><a href="{{ route('admin.menus.edit', $menu) }}">Редактирай</a></td>
                </tr>
            @empty
                <tr><td colspan="4">Няма менюта.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
