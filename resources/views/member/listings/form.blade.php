@extends('layouts.site')

@php use App\Enums\CustomFieldType; @endphp

@section('title', ($listing ? 'Редакция на обява' : 'Нова обява').' — '.config('app.name', 'ListingHub'))

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-1 text-2xl font-semibold tracking-tight">
            {{ $listing ? 'Редакция на обява' : 'Нова обява' }}
        </h1>

        @if ($listing)
            <p class="mb-6 flex gap-4 text-sm">
                <a href="{{ route('member.listings.products.index', $listing) }}" class="text-slate-600 underline hover:text-slate-900">Продукти</a>
                <a href="{{ route('member.listings.hours.edit', $listing) }}" class="text-slate-600 underline hover:text-slate-900">Работно време</a>
                <a href="{{ route('member.listings.leads.index', $listing) }}" class="text-slate-600 underline hover:text-slate-900">Запитвания</a>
            </p>
        @endif

        @include('member.partials.flash')

        {{-- Choosing the category first is what determines which custom fields
             exist, so it is a separate step rather than part of the main form. --}}
        @if ($category === null)
            <form method="GET" action="{{ route('member.listings.create') }}"
                  class="rounded-lg border border-slate-200 bg-white p-6">
                <label for="category" class="block text-sm font-medium">Изберете категория</label>
                <p class="mt-1 text-sm text-slate-600">
                    Категорията определя допълнителните полета на обявата.
                </p>
                <select id="category" name="category" required data-tom-select
                        class="mt-3 w-full rounded-md border border-slate-300 px-3 py-2">
                    <option value="">—</option>
                    @foreach ($categories as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                <button type="submit"
                        class="mt-4 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Продължи
                </button>
            </form>
        @else
            <form method="POST"
                  action="{{ $listing ? route('member.listings.update', $listing) : route('member.listings.store') }}"
                  class="space-y-6">
                @csrf
                @if ($listing) @method('PUT') @endif
                <input type="hidden" name="category_id" value="{{ old('category_id', $category->id) }}">

                <section class="rounded-lg border border-slate-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-semibold">Основни данни</h2>
                    <p class="mb-4 text-sm text-slate-600">Категория: {{ $category->name }}</p>

                    <div class="space-y-4">
                        <div>
                            <label for="title" class="block text-sm font-medium">Заглавие</label>
                            <input id="title" name="title" type="text" required maxlength="255"
                                   value="{{ old('title', $listing?->title) }}"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            @error('title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium">Описание</label>
                            <textarea id="description" name="description" rows="6" maxlength="10000"
                                      class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">{{ old('description', $listing?->description) }}</textarea>
                            @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                @include('member.partials.location-picker')

                <section class="rounded-lg border border-slate-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-semibold">Контакти</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="block text-sm font-medium">Телефон</label>
                            <input id="phone" name="phone" type="tel" maxlength="40"
                                   value="{{ old('phone', $listing?->phone) }}"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            @error('phone') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium">Имейл</label>
                            <input id="email" name="email" type="email" maxlength="255"
                                   value="{{ old('email', $listing?->email) }}"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="website" class="block text-sm font-medium">Уебсайт</label>
                            <input id="website" name="website" type="url" maxlength="255"
                                   placeholder="https://" aria-describedby="website-hint"
                                   value="{{ old('website', $listing?->website) }}"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <p id="website-hint" class="mt-1 text-xs text-slate-500">Само http:// или https://</p>
                            @error('website') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="address" class="block text-sm font-medium">Адрес</label>
                            <input id="address" name="address" type="text" maxlength="255"
                                   value="{{ old('address', $listing?->address) }}"
                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            @error('address') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                @if ($fields->isNotEmpty())
                    <section class="rounded-lg border border-slate-200 bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold">Полета за „{{ $category->name }}”</h2>

                        <div class="space-y-4">
                            @foreach ($fields as $field)
                                @php
                                    $name = "custom_fields[{$field->key}]";
                                    $col = $field->type->valueColumn();
                                    $current = old("custom_fields.{$field->key}", $values[$field->id]->{$col} ?? null);
                                    $err = $errors->first("custom_fields.{$field->key}");
                                @endphp

                                <div>
                                    <label for="cf_{{ $field->key }}" class="block text-sm font-medium">
                                        {{ $field->label }}@if ($field->is_required)<span class="text-red-700" aria-hidden="true">*</span>@endif
                                    </label>

                                    @switch($field->type)
                                        @case(CustomFieldType::Number)
                                            <input id="cf_{{ $field->key }}" name="{{ $name }}" type="text"
                                                   inputmode="decimal" value="{{ $current }}"
                                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                                            @break
                                        @case(CustomFieldType::Checkbox)
                                            <input id="cf_{{ $field->key }}" name="{{ $name }}" type="checkbox"
                                                   value="1" @checked($current)
                                                   class="mt-1 h-4 w-4 rounded border-slate-300">
                                            @break
                                        @case(CustomFieldType::Select)
                                            <select id="cf_{{ $field->key }}" name="{{ $name }}"
                                                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                                                <option value="">—</option>
                                                @foreach ((array) $field->options as $opt)
                                                    <option value="{{ $opt['value'] }}"
                                                            @selected((string) $current === (string) $opt['value'])>
                                                        {{ $opt['label'] ?? $opt['value'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @break
                                        @default
                                            <input id="cf_{{ $field->key }}" name="{{ $name }}" type="text"
                                                   value="{{ $current }}"
                                                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                                    @endswitch

                                    @if ($err)
                                        <p class="mt-1 text-sm text-red-700" role="alert">{{ $err }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        {{ $listing ? 'Запази' : 'Създай' }}
                    </button>
                    <a href="{{ route('member.listings.index') }}"
                       class="text-sm text-slate-600 underline hover:text-slate-900">Отказ</a>
                </div>
            </form>

            {{-- Outside the main form: nested forms are invalid HTML, and the
                 gallery needs a persisted listing to attach assets to. --}}
            @if ($listing)
                <div class="mt-6">
                    @include('member.partials.gallery')
                </div>
            @endif
        @endif
    </div>
@endsection
