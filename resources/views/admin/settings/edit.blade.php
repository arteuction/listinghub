@extends('admin.layout')

@section('title', 'Настройки')

@section('content')
    <h1>Настройки</h1>

    @include('admin.partials.flash')

    <p>
        Тук са само настройките, които реално променят поведението на приложението.
        Стойностите по подразбиране идват от <code>config/listinghub.php</code>; запазването тук
        ги замества за цялата инсталация.
    </p>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <h2>Модерация</h2>

        @foreach ($booleans as $key => $meta)
            <p>
                <label>
                    <input type="hidden" name="settings[{{ $key }}]" value="0">
                    <input type="checkbox" name="settings[{{ $key }}]" value="1" @checked($values[$key])>
                    {{ $meta['label'] }}
                </label>
            </p>
        @endforeach

        <button type="submit">Запази</button>
    </form>
@endsection
