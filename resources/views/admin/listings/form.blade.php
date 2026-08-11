@extends('admin.layout')

@section('title', $listing ? 'Edit listing' : 'New listing')

@php use App\Enums\CustomFieldType; @endphp

@section('content')
    <h1>{{ $listing ? 'Edit' : 'New' }} listing — {{ $category->name }}</h1>

    @if ($errors->any())
        <div class="alert" role="alert">
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $listing ? route('admin.listings.update', $listing) : route('admin.listings.store') }}">
        @csrf
        @if ($listing) @method('PUT') @endif
        <input type="hidden" name="category_id" value="{{ old('category_id', $category->id) }}">

        <label for="title">Title</label>
        <input id="title" name="title" value="{{ old('title', $listing?->title) }}" required>

        <fieldset>
            <legend>{{ $category->name }} fields</legend>

            @foreach ($fields as $field)
                @php
                    $name = "custom_fields[{$field->key}]";
                    $col = $field->type->valueColumn();
                    $current = old("custom_fields.{$field->key}", $values[$field->id]->{$col} ?? null);
                    $err = $errors->first("custom_fields.{$field->key}");
                @endphp

                <div>
                    <label for="cf_{{ $field->key }}">
                        {{ $field->label }}@if ($field->is_required) <span aria-hidden="true">*</span>@endif
                    </label>

                    @switch($field->type)
                        @case(CustomFieldType::Number)
                            <input id="cf_{{ $field->key }}" name="{{ $name }}" type="text" inputmode="decimal" value="{{ $current }}">
                            @break
                        @case(CustomFieldType::Checkbox)
                            <input id="cf_{{ $field->key }}" name="{{ $name }}" type="checkbox" value="1" @checked($current)>
                            @break
                        @case(CustomFieldType::Select)
                            <select id="cf_{{ $field->key }}" name="{{ $name }}">
                                <option value="">—</option>
                                @foreach ((array) $field->options as $opt)
                                    <option value="{{ $opt['value'] }}" @selected((string) $current === (string) $opt['value'])>{{ $opt['label'] ?? $opt['value'] }}</option>
                                @endforeach
                            </select>
                            @break
                        @default
                            <input id="cf_{{ $field->key }}" name="{{ $name }}" type="text" value="{{ $current }}">
                    @endswitch

                    @if ($err)
                        <span class="bad" role="alert">{{ $err }}</span>
                    @endif
                </div>
            @endforeach
        </fieldset>

        <button type="submit" class="btn">{{ $listing ? 'Save' : 'Create' }}</button>
    </form>
@endsection
