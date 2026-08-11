@extends('admin.layout')

@section('title', 'Категории')

@section('content')
    <h1>Категории</h1>

    @include('admin.partials.flash')

    <p><a href="{{ route('admin.categories.create') }}">Нова категория</a></p>

    @if ($categories->isEmpty())
        <p>Няма категории.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Име</th><th>Слъг</th><th>Родител</th><th>Подкатегории</th>
                    <th>Обяви</th><th>Ред</th><th>Активна</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->slug }}</td>
                        <td>{{ $categories->firstWhere('id', $category->parent_id)?->name ?? '—' }}</td>
                        <td>{{ $category->children_count }}</td>
                        <td>{{ $category->listings_count }}</td>
                        <td>{{ $category->sort_order }}</td>
                        <td>{{ $category->is_active ? 'да' : 'не' }}</td>
                        <td>
                            <a href="{{ route('admin.categories.edit', $category) }}">Редакция</a>
                            <a href="{{ route('admin.custom-fields.index', $category) }}">Полета</a>

                            {{-- Delete is offered only when it can succeed; the
                                 controller re-checks, since this is a render. --}}
                            @if ($category->children_count === 0 && $category->listings_count === 0)
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                      onsubmit="return confirm('Да се изтрие ли категорията {{ $category->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">Изтрий</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
