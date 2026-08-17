@extends('admin.layout')

@section('title', 'Термини — '.$taxonomy->name)

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h1>{{ $taxonomy->name }}: термини</h1>
        <a href="{{ route('admin.taxonomy.terms.create', $taxonomy) }}">+ Нов термин</a>
    </div>

    <p><a href="{{ route('admin.taxonomy.index') }}">← Обратно към Taxonomy</a></p>

    @include('admin.partials.flash')

    @if ($roots->isEmpty())
        <p>Няма термини. <a href="{{ route('admin.taxonomy.terms.create', $taxonomy) }}">Добави първия.</a></p>
    @else
        @include('admin.taxonomy.partials.term-tree', ['terms' => $roots, 'taxonomy' => $taxonomy, 'depth' => 0])
    @endif
@endsection
