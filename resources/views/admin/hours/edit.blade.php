@extends('admin.layout')

@php
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
@endphp

@section('title', 'Hours — '.$listing->title)

@section('content')
    <h1>Hours — {{ $listing->title }}</h1>

    @if ($errors->any())
        <div class="alert" role="alert">
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.listings.hours.update', $listing) }}">
        @csrf
        @method('PUT')

        <table>
            <thead>
                <tr><th>Day</th><th>Closed</th><th>Opens</th><th>Closes</th></tr>
            </thead>
            <tbody>
                @foreach ($dayNames as $dow => $name)
                    @php $hour = $hours[$dow] ?? null; @endphp
                    <tr>
                        <td>
                            {{ $name }}
                            <input type="hidden" name="days[{{ $dow }}][day_of_week]" value="{{ $dow }}">
                        </td>
                        <td>
                            <input type="checkbox" name="days[{{ $dow }}][is_closed]" value="1"
                                   @checked(old("days.{$dow}.is_closed", $hour?->is_closed))>
                        </td>
                        <td>
                            <input type="time" name="days[{{ $dow }}][opens_at]"
                                   value="{{ old("days.{$dow}.opens_at", $hour?->opens_at) }}">
                        </td>
                        <td>
                            <input type="time" name="days[{{ $dow }}][closes_at]"
                                   value="{{ old("days.{$dow}.closes_at", $hour?->closes_at) }}">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <button type="submit" class="btn">Save hours</button>
    </form>

    <h2>Date exceptions</h2>

    @if ($exceptions->isEmpty())
        <p>No exceptions yet.</p>
    @else
        <ul>
            @foreach ($exceptions as $exception)
                <li>
                    {{ $exception->date->format('Y-m-d') }} —
                    {{ $exception->is_closed ? 'Closed' : $exception->opens_at.'–'.$exception->closes_at }}
                    @if ($exception->note) ({{ $exception->note }}) @endif
                    <form method="POST" action="{{ route('admin.listings.hours.exceptions.destroy', [$listing, $exception]) }}" style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.listings.hours.exceptions.store', $listing) }}">
        @csrf

        <label for="date">Date</label>
        <input id="date" name="date" type="date" value="{{ old('date') }}" required>

        <label><input type="checkbox" name="is_closed" value="1" @checked(old('is_closed', true))> Closed all day</label>

        <label for="opens_at">Opens</label>
        <input id="opens_at" name="opens_at" type="time" value="{{ old('opens_at') }}">

        <label for="closes_at">Closes</label>
        <input id="closes_at" name="closes_at" type="time" value="{{ old('closes_at') }}">

        <label for="note">Note</label>
        <input id="note" name="note" maxlength="255" value="{{ old('note') }}">

        <button type="submit" class="btn">Add exception</button>
    </form>
@endsection
