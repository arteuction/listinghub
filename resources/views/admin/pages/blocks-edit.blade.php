@extends('admin.layout')

@section('title', 'Редактирай блок')

@section('content')
    <h1>Редактирай блок: {{ $block->block_type->value }}</h1>
    <p>
        <a href="{{ route('admin.pages.blocks', $page) }}">← Назад към блоковете</a>
        · Версия: {{ $block->version }}
        · Статус: {{ $block->status->value }}
    </p>

    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.pages.blocks.update', [$page, $block]) }}">
        @csrf @method('PUT')

        @if ($block->block_type === \App\Enums\ContentBlockType::RichText)
            <x-admin.partials.rich-text-editor
                inputName="content[tiptap]"
                inputId="content_tiptap"
                :value="$block->content['tiptap'] ?? null"
                label="Съдържание"
            />
        @else
            <p><em>Блоков тип „{{ $block->block_type->value }}" — редактирането на нетекстови блокове ще бъде добавено в следваща итерация.</em></p>
            <textarea name="content_json" rows="12" style="width:100%;font-family:monospace;font-size:.8rem;"
                      readonly>{{ json_encode($block->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
        @endif

        <p style="margin-top:1rem;">
            <button type="submit">Запази</button>
            <a href="{{ route('admin.pages.blocks', $page) }}" style="margin-left:.5rem;">Отказ</a>
        </p>
    </form>

    @if ($block->revisions->isNotEmpty())
        <h2 style="margin-top:2rem;">Ревизии</h2>
        <table>
            <thead><tr><th>Версия</th><th>Операция</th><th>Дата</th></tr></thead>
            <tbody>
                @foreach ($block->revisions as $rev)
                    <tr>
                        <td>{{ $rev->version }}</td>
                        <td>{{ $rev->operation }}</td>
                        <td>{{ $rev->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
