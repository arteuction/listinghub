@extends('admin.layout')

@section('title', $page ? 'Редактирай страница' : 'Нова страница')

@section('content')
    <h1>{{ $page ? 'Редактирай: '.$page->title : 'Нова страница' }}</h1>

    @include('admin.partials.flash')

    <form method="POST"
          action="{{ $page ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
        @csrf
        @if ($page) @method('PUT') @endif

        <p>
            <label for="title">Заглавие <abbr title="задължително">*</abbr></label><br>
            <input id="title" name="title" type="text" required maxlength="255"
                   value="{{ old('title', $page?->title) }}" style="width:100%;max-width:480px;">
            @error('title')<span role="alert" style="color:red"> {{ $message }}</span>@enderror
        </p>

        @if (! $page?->is_system)
            <p>
                <label for="slug">Slug (само малки букви и тире)</label><br>
                <input id="slug" name="slug" type="text" maxlength="255"
                       pattern="[a-z0-9-]+"
                       value="{{ old('slug', $page?->slug) }}" style="width:100%;max-width:480px;">
                @error('slug')<span role="alert" style="color:red"> {{ $message }}</span>@enderror
            </p>
        @else
            <p><strong>Slug:</strong> <code>{{ $page->slug }}</code> (системни slug-ове са непроменими)</p>
        @endif

        <p>
            <label for="sort_order">Подредба</label><br>
            <input id="sort_order" name="sort_order" type="number" min="0"
                   value="{{ old('sort_order', $page?->sort_order ?? 1000) }}" style="width:120px;">
        </p>

        <p>
            <button type="submit">{{ $page ? 'Запази' : 'Създай' }}</button>
            <a href="{{ route('admin.pages.index') }}" style="margin-left:.5rem;">Отказ</a>
        </p>
    </form>

    @if ($page)
        <hr style="margin:2rem 0;">
        <p>
            <a href="{{ route('admin.pages.blocks', $page) }}">Управление на блокове</a>
            ·
            <a href="{{ route('admin.pages.seo.edit', $page) }}">SEO настройки</a>
            ·
            <form method="POST" action="{{ route('admin.pages.preview', $page) }}" style="display:inline">
                @csrf
                <button type="submit">Генерирай Preview</button>
            </form>
        </p>
    @endif
@endsection
