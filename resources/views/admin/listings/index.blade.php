@extends('admin.layout')

@section('title', 'Обяви')

@section('content')
    <h1>Обяви</h1>

    @include('admin.partials.flash')

    <form method="GET" action="{{ route('admin.listings.index') }}">
        <label for="q">Търсене</label>
        <input id="q" name="q" type="search" value="{{ $term }}" placeholder="заглавие">

        <label for="status">Статус</label>
        <select id="status" name="status">
            <option value="">Всички</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>

        <button type="submit">Филтрирай</button>
    </form>

    @if ($listings->isEmpty())
        <p>Няма обяви по този филтър.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Заглавие</th><th>Категория</th><th>Собственик</th><th>Статус</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($listings as $listing)
                    <tr>
                        <td>{{ $listing->title }}</td>
                        <td>{{ $listing->category?->name ?? '—' }}</td>
                        <td>{{ $listing->owner?->name ?? '—' }}</td>
                        <td>{{ $listing->status->label() }}</td>
                        <td>
                            <a href="{{ route('admin.listings.edit', $listing) }}">Редакция</a>
                            <a href="{{ route('admin.listings.products.index', $listing) }}">Продукти</a>
                            <a href="{{ route('admin.listings.hours.edit', $listing) }}">Часове</a>
                            @include('admin.partials.listing-transitions', ['listing' => $listing])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $listings->links() }}
    @endif
@endsection
