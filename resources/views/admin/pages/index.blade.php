@extends('admin.layout')

@section('title', 'Страници')

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h1>Страници</h1>
        <a href="{{ route('admin.pages.create') }}">+ Нова страница</a>
    </div>

    @include('admin.partials.flash')

    <table>
        <thead>
            <tr>
                <th>Заглавие</th>
                <th>Slug</th>
                <th>Статус</th>
                <th>Система</th>
                <th>Подредба</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pages as $page)
                <tr>
                    <td>{{ $page->title }}</td>
                    <td><code>{{ $page->slug }}</code></td>
                    <td>{{ $page->status->value }}</td>
                    <td>{{ $page->is_system ? 'Да' : '—' }}</td>
                    <td>{{ $page->sort_order }}</td>
                    <td style="white-space:nowrap;">
                        <a href="{{ route('admin.pages.edit', $page) }}">Редактирай</a>
                        ·
                        <a href="{{ route('admin.pages.blocks', $page) }}">Блокове</a>
                        ·
                        <a href="{{ route('admin.pages.seo.edit', $page) }}">SEO</a>
                        ·
                        @if ($page->isPublished())
                            <form method="POST" action="{{ route('admin.pages.unpublish', $page) }}" style="display:inline">
                                @csrf
                                <button type="submit">Скрий</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.pages.publish', $page) }}" style="display:inline">
                                @csrf
                                <button type="submit">Публикувай</button>
                            </form>
                        @endif
                        @unless ($page->is_system)
                            ·
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" style="display:inline"
                                  onsubmit="return confirm('Изтрий страницата?')">
                                @csrf @method('DELETE')
                                <button type="submit">Изтрий</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Няма страници.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
