@extends('admin.layout')

@section('title', 'Запитвания')

@section('content')
    <h1>Запитвания</h1>

    @include('admin.partials.flash')

    <form method="GET" action="{{ route('admin.leads.index') }}">
        <label for="q">Търсене</label>
        <input id="q" name="q" type="search" value="{{ $term }}" placeholder="име, имейл или текст">

        <label>
            <input type="checkbox" name="unread" value="1" @checked($unreadOnly)>
            само непрочетени
        </label>

        <button type="submit">Филтрирай</button>
    </form>

    @if ($leads->isEmpty())
        <p>Няма запитвания по този филтър.</p>
    @else
        <table>
            <thead>
                <tr><th>Обява</th><th>От</th><th>Съобщение</th><th>Получено</th><th>Прочетено</th><th>Действия</th></tr>
            </thead>
            <tbody>
                @foreach ($leads as $lead)
                    <tr>
                        <td>{{ $lead->listing?->title ?? '—' }}</td>
                        <td>
                            {{ $lead->name }}<br>
                            <small>{{ $lead->email }}{{ $lead->phone ? ' · '.$lead->phone : '' }}</small>
                        </td>
                        <td>{{ $lead->message }}</td>
                        <td>{{ $lead->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $lead->read_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>
                            @if ($lead->read_at === null)
                                <form method="POST" action="{{ route('admin.leads.read', $lead) }}">
                                    @csrf
                                    <button type="submit">Отбележи прочетено</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.leads.destroy', $lead) }}"
                                  onsubmit="return confirm('Да се изтрие ли запитването?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Изтрий</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $leads->links() }}
    @endif
@endsection
