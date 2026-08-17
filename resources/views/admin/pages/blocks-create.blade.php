@extends('admin.layout')

@section('title', 'Нов блок — '.$page->title)

@section('content')
    <h1>Нов блок: {{ $page->title }}</h1>
    <p><a href="{{ route('admin.pages.blocks', $page) }}">← Назад</a></p>

    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.pages.blocks.store', $page) }}">
        @csrf

        <p>
            <label for="block_type">Тип блок <abbr title="задължително">*</abbr></label><br>
            <select id="block_type" name="block_type" required
                    x-data x-on:change="$dispatch('block-type-changed', {type: $event.target.value})">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" {{ old('block_type') === $type->value ? 'selected' : '' }}>
                        {{ $type->value }}
                    </option>
                @endforeach
            </select>
        </p>

        {{-- Rich text (shown for rich_text blocks) --}}
        <div id="tiptap-section">
            <x-admin.partials.rich-text-editor
                inputName="content[tiptap]"
                inputId="content_tiptap"
                :value="old('content.tiptap')"
                label="Съдържание (Rich Text)"
                placeholder="Въведете текст…"
            />
        </div>

        <p>
            <label for="sort_order">Подредба</label><br>
            <input id="sort_order" name="sort_order" type="number" min="0"
                   value="{{ old('sort_order', 1000) }}" style="width:120px;">
        </p>

        <p>
            <button type="submit">Добави блок</button>
            <a href="{{ route('admin.pages.blocks', $page) }}" style="margin-left:.5rem;">Отказ</a>
        </p>
    </form>
@endsection
