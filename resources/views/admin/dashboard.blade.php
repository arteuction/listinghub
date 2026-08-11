@extends('admin.layout')

@section('title', 'Табло')

@section('content')
    <h1>Табло</h1>
    <p>Добре дошли, {{ auth()->user()->name }}.</p>

    @include('admin.partials.flash')

    <h2>Чакащи решение</h2>
    <ul>
        <li>
            <a href="{{ route('admin.moderation.index') }}">Модерация</a>:
            {{ $listings[\App\Enums\ListingStatus::Pending->value] ?? 0 }} обяви,
            {{ $reviews[\App\Enums\ModerationStatus::Pending->value] ?? 0 }} отзива,
            {{ $claims[\App\Enums\ModerationStatus::Pending->value] ?? 0 }} заявки за собственост
        </li>
        <li>
            <a href="{{ route('admin.leads.index', ['unread' => 1]) }}">Непрочетени запитвания</a>:
            {{ $unreadLeads }} от {{ $totalLeads }}
        </li>
    </ul>

    <h2>Обяви</h2>
    <table>
        <thead><tr><th>Статус</th><th>Брой</th></tr></thead>
        <tbody>
            @foreach ($listingStatuses as $case)
                <tr>
                    <td><a href="{{ route('admin.listings.index', ['status' => $case->value]) }}">{{ $case->label() }}</a></td>
                    <td>{{ $listings[$case->value] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Потребители</h2>
    <table>
        <thead><tr><th>Статус</th><th>Брой</th></tr></thead>
        <tbody>
            @foreach ($userStatuses as $case)
                <tr>
                    <td>{{ $case->value }}</td>
                    <td>{{ $users[$case->value] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Отзиви и заявки</h2>
    <table>
        <thead><tr><th>Статус</th><th>Отзиви</th><th>Заявки за собственост</th></tr></thead>
        <tbody>
            @foreach ($moderationStatuses as $case)
                <tr>
                    <td>{{ $case->value }}</td>
                    <td>{{ $reviews[$case->value] ?? 0 }}</td>
                    <td>{{ $claims[$case->value] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
