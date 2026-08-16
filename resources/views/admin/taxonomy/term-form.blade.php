@extends('admin.layout')

@section('title', $term ? 'Редактирай термин' : 'Нов термин')

@section('content')
    <h1>{{ $term ? 'Редактирай: '.$term->name : 'Нов термин — '.$taxonomy->name }}</h1>
    <p><a href="{{ route('admin.taxonomy.terms', $taxonomy) }}">← Назад</a></p>

    @include('admin.partials.flash')

    <form method="POST"
          action="{{ $term
              ? route('admin.taxonomy.terms.update', [$taxonomy, $term])
              : route('admin.taxonomy.terms.store', $taxonomy) }}">
        @csrf
        @if ($term) @method('PUT') @endif

        <p>
            <label for="name">Наименование <abbr title="задължително">*</abbr></label><br>
            <input id="name" name="name" type="text" required maxlength="255"
                   value="{{ old('name', $term?->name) }}" style="width:100%;max-width:400px;">
            @error('name')<span role="alert" style="color:red"> {{ $message }}</span>@enderror
        </p>

        @unless ($term)
            <p>
                <label for="slug">Slug</label><br>
                <input id="slug" name="slug" type="text" maxlength="255"
                       pattern="[a-z0-9-]+" value="{{ old('slug') }}" style="width:100%;max-width:400px;">
                @error('slug')<span role="alert" style="color:red"> {{ $message }}</span>@enderror
            </p>
        @endunless

        @if ($taxonomy->is_hierarchical && $parents->isNotEmpty())
            <p>
                <label for="parent_id">Родителски термин</label><br>
                <select id="parent_id" name="parent_id" style="width:100%;max-width:400px;">
                    <option value="">— корен —</option>
                    @foreach ($parents as $p)
                        <option value="{{ $p->id }}"
                            {{ old('parent_id', $term?->parent_id) == $p->id ? 'selected' : '' }}>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </p>
        @endif

        <p>
            <label for="icon">Икона (emoji или клас)</label><br>
            <input id="icon" name="icon" type="text" maxlength="64"
                   value="{{ old('icon', $term?->icon) }}" style="width:120px;">
        </p>

        <p>
            <label for="sort_order">Подредба</label><br>
            <input id="sort_order" name="sort_order" type="number" min="0"
                   value="{{ old('sort_order', $term?->sort_order ?? 1000) }}" style="width:120px;">
        </p>

        <p>
            <button type="submit">{{ $term ? 'Запази' : 'Създай' }}</button>
            <a href="{{ route('admin.taxonomy.terms', $taxonomy) }}" style="margin-left:.5rem;">Отказ</a>
        </p>
    </form>

    @if ($term && $taxonomy->is_hierarchical)
        <hr style="margin:2rem 0;">
        <h2>Премести</h2>
        <form method="POST" action="{{ route('admin.taxonomy.terms.move', [$taxonomy, $term]) }}">
            @csrf
            <label for="move_parent_id">Нов родител<br>
                <select id="move_parent_id" name="parent_id" style="width:100%;max-width:400px;">
                    <option value="">— корен —</option>
                    @foreach ($parents as $p)
                        <option value="{{ $p->id }}"
                            {{ $term->parent_id == $p->id ? 'selected' : '' }}>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <p><button type="submit">Премести</button></p>
        </form>
    @endif
@endsection
