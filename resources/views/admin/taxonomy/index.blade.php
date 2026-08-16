@extends('admin.layout')

@section('title', 'Taxonomy')

@section('content')
    <h1>Taxonomy vocabularies</h1>

    @include('admin.partials.flash')

    <table>
        <thead>
            <tr>
                <th>Наименование</th>
                <th>Slug</th>
                <th>Контекст</th>
                <th>Йерархичен</th>
                <th>Множество</th>
                <th>Термини</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($taxonomies as $taxonomy)
                <tr>
                    <td>{{ $taxonomy->name }}</td>
                    <td><code>{{ $taxonomy->slug }}</code></td>
                    <td>{{ $taxonomy->context }}</td>
                    <td>{{ $taxonomy->is_hierarchical ? 'Да' : '—' }}</td>
                    <td>{{ $taxonomy->allow_multiple ? 'Да' : '—' }}</td>
                    <td>{{ $taxonomy->terms_count }}</td>
                    <td><a href="{{ route('admin.taxonomy.terms', $taxonomy) }}">Термини</a></td>
                </tr>
            @empty
                <tr><td colspan="7">Няма taxonomies.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
