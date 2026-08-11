@extends('admin.layout')

@section('title', 'Custom fields')

@section('content')
    <h1>Custom fields — {{ $category->name }}</h1>

    <p><a class="btn" href="{{ route('admin.custom-fields.create', $category) }}">New field</a></p>

    @if ($errors->any())
        <div class="alert" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($fields->isEmpty())
        <p>No custom fields yet.</p>
    @else
        <table>
            <thead>
                <tr><th>Label</th><th>Key</th><th>Type</th><th>Flags</th><th>Order</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($fields as $field)
                    <tr>
                        <td>{{ $field->label }}</td>
                        <td><code>{{ $field->key }}</code></td>
                        <td>{{ $field->type->value }}</td>
                        <td>
                            {{ $field->searchable ? 'search ' : '' }}{{ $field->filterable ? 'filter ' : '' }}{{ $field->sortable ? 'sort' : '' }}
                        </td>
                        <td>{{ $field->sort_order }}</td>
                        <td>
                            <a href="{{ route('admin.custom-fields.edit', [$category, $field]) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.custom-fields.destroy', [$category, $field]) }}" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
