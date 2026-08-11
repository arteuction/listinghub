@extends('admin.layout')

@section('title', $field ? 'Edit field' : 'New field')

@section('content')
    <h1>{{ $field ? 'Edit' : 'New' }} custom field — {{ $category->name }}</h1>

    @if ($errors->any())
        <div class="alert" role="alert">
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $field
              ? route('admin.custom-fields.update', [$category, $field])
              : route('admin.custom-fields.store', $category) }}">
        @csrf
        @if ($field) @method('PUT') @endif

        <label for="label">Label</label>
        <input id="label" name="label" value="{{ old('label', $field?->label) }}" required>

        <label for="key">Key</label>
        <input id="key" name="key" value="{{ old('key', $field?->key) }}" required>

        <label for="type">Type</label>
        <select id="type" name="type">
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $field?->type?->value) === $type->value)>{{ $type->value }}</option>
            @endforeach
        </select>

        <fieldset>
            <legend>Options</legend>
            <label><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $field?->is_required))> Required</label>
            <label><input type="checkbox" name="searchable" value="1" @checked(old('searchable', $field?->searchable))> Searchable</label>
            <label><input type="checkbox" name="filterable" value="1" @checked(old('filterable', $field?->filterable))> Filterable</label>
            <label><input type="checkbox" name="sortable" value="1" @checked(old('sortable', $field?->sortable))> Sortable</label>
        </fieldset>

        <label for="sort_order">Sort order</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $field?->sort_order ?? 0) }}">

        <p><small>Select options are edited as JSON for now (e.g. <code>[{"value":"red","label":"Red"}]</code>).</small></p>

        <button type="submit" class="btn">{{ $field ? 'Save' : 'Create' }}</button>
    </form>
@endsection
