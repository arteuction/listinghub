@extends('admin.layout')

@section('title', 'Блокове — '.$page->title)

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h1>Блокове: {{ $page->title }}</h1>
        <a href="{{ route('admin.pages.blocks.create', $page) }}">+ Нов блок</a>
    </div>

    <p>
        <a href="{{ route('admin.pages.edit', $page) }}">← Назад към страницата</a>
    </p>

    @include('admin.partials.flash')

    @if ($blocks->isEmpty())
        <p>Няма блокове. <a href="{{ route('admin.pages.blocks.create', $page) }}">Добави първия.</a></p>
    @else
        {{-- Drag-and-drop reorder list --}}
        <ul id="block-list" style="list-style:none;padding:0;" aria-label="Блокове за пренареждане">
            @foreach ($blocks as $block)
                <li data-uuid="{{ $block->uuid }}"
                    style="display:flex;align-items:center;gap:.75rem;padding:.5rem;border:1px solid #cbd5e1;margin-bottom:.5rem;background:#fff;cursor:grab;">
                    <span aria-hidden="true" title="Влачи за пренареждане">⠿</span>
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

        <script>
        (function () {
            const list = document.getElementById('block-list');
            if (!list) return;

            let dragged = null;

            list.addEventListener('dragstart', e => {
                dragged = e.target.closest('li');
                dragged.style.opacity = '.4';
                e.dataTransfer.effectAllowed = 'move';
            });
            list.addEventListener('dragend', e => {
                e.target.closest('li').style.opacity = '';
                saveOrder();
            });
            list.addEventListener('dragover', e => {
                e.preventDefault();
                const target = e.target.closest('li');
                if (target && target !== dragged) {
                    const rect = target.getBoundingClientRect();
                    const after = e.clientY > rect.top + rect.height / 2;
                    list.insertBefore(dragged, after ? target.nextSibling : target);
                }
            });

            list.querySelectorAll('li').forEach(li => li.setAttribute('draggable', 'true'));

            function saveOrder() {
                const ids = Array.from(list.querySelectorAll('li')).map(li => li.dataset.uuid);
                fetch('{{ route('admin.pages.blocks.reorder', $page) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                                     || '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ ids }),
                });
            }
        })();
        </script>
    @endif
@endsection
