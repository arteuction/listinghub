@extends('admin.layout')

@section('title', $category ? 'Редакция на категория' : 'Нова категория')

@section('content')
    <h1>{{ $category ? 'Редакция на категория' : 'Нова категория' }}</h1>

    @include('admin.partials.flash')

    <form method="POST"
          action="{{ $category ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf
        @if ($category)
            @method('PUT')
        @endif

        <p>
            <label for="name">Име</label><br>
            <input id="name" name="name" type="text" required maxlength="255"
                   value="{{ old('name', $category->name ?? '') }}">
        </p>

        <p>
            <label for="slug">Слъг</label><br>
            <input id="slug" name="slug" type="text" required maxlength="255"
                   value="{{ old('slug', $category->slug ?? '') }}">
            <small>малки латински букви, цифри и тире</small>
        </p>

        <p>
            <label for="parent_id">Родителска категория</label><br>
            <select id="parent_id" name="parent_id" data-tom-select>
                <option value="">— няма (основна) —</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((int) old('parent_id', $category->parent_id ?? 0) === $parent->id)>
                        {{ $parent->name }}
                    </option>
                @endforeach
            </select>
            {{-- The category's own subtree is absent from this list: a category
                 cannot be placed under itself or under one of its descendants. --}}
        </p>

        <p>
            <label for="icon">Икона</label><br>
            <input id="icon" name="icon" type="text" maxlength="255" value="{{ old('icon', $category->icon ?? '') }}">
        </p>

        <p>
            <label for="sort_order">Ред на подреждане</label><br>
            <input id="sort_order" name="sort_order" type="number" min="0" max="65535" required
                   value="{{ old('sort_order', $category->sort_order ?? 0) }}">
        </p>

        <p>
            <label>
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $category->is_active ?? true))>
                Активна (видима в публичния каталог)
            </label>
        </p>

        <button type="submit">Запази</button>
        <a href="{{ route('admin.categories.index') }}">Отказ</a>
    </form>
@endsection
