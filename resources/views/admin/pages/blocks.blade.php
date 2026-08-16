@extends('admin.layout')

@section('title', 'Блокове — '.$page->title)

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h1>Блокове: {{ $page->title }}</h1>
        <a href="{{ route('admin.pages.blocks.create', $page) }}">+ Нов блок</a>
    </div>

    <p>
        <a href="{{ route('admin.pages.edit', $page) }}">← Назад към страницата</a>
        ·
        @if ($page->isPublished())
            <a href="{{ route('pages.show', $page->slug) }}" target="_blank" rel="noopener">Виж публично ↗</a>
        @endif
    </p>

    @include('admin.partials.flash')

    @if ($blocks->isEmpty())
        <p>Няма блокове. <a href="{{ route('admin.pages.blocks.create', $page) }}">Добави първия.</a></p>
    @else
        <ul id="block-list"
            data-sortable
            data-sortable-url="{{ route('admin.pages.blocks.reorder', $page) }}"
            data-sortable-id="uuid"
            style="list-style:none;padding:0;"
            aria-label="Блокове за пренареждане">
            @foreach ($blocks as $block)
                <li data-uuid="{{ $block->uuid }}"
                    style="display:flex;align-items:center;gap:.75rem;padding:.5rem;border:1px solid #cbd5e1;margin-bottom:.5rem;background:#fff;">
                    <span data-drag-handle
                          aria-hidden="true"
                          title="Влачи за пренареждане"
                          style="cursor:grab;color:#94a3b8;user-select:none;">⠿</span>
                    <span style="flex:1;">
                        <strong>{{ $block->block_type->value }}</strong>
                        <small style="color:#64748b;margin-left:.5rem;">{{ $block->status->value }}</small>
                    </span>
                    <a href="{{ route('admin.pages.blocks.edit', [$page, $block]) }}">Редактирай</a>
                    @if (! $block->status->isPublic())
                        <form method="POST" action="{{ route('admin.pages.blocks.publish', [$page, $block]) }}" style="display:inline">
                            @csrf
                            <button type="submit">Публикувай</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.pages.blocks.destroy', [$page, $block]) }}"
                          style="display:inline" onsubmit="return confirm('Изтрий блока?')">
                        @csrf @method('DELETE')
                        <button type="submit">Изтрий</button>
                    </form>
                </li>
            @endforeach
        </ul>
        <p style="color:#94a3b8;font-size:.8rem;">Влачете редовете за да подредите блоковете. Редът се записва автоматично.</p>
    @endif
@endsection
