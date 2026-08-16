@extends('admin.layout')

@section('title', 'SEO настройки')

@section('content')
    <h1>SEO настройки</h1>

    @include('admin.partials.flash')

    <form method="POST" action="{{ $formAction }}">
        @csrf @method('PUT')

        <p>
            <label for="meta_title">Meta заглавие (до 120 знака)</label><br>
            <input id="meta_title" name="meta_title" type="text" maxlength="120"
                   value="{{ old('meta_title', $seo?->meta_title) }}"
                   style="width:100%;max-width:560px;">
            @error('meta_title')<span role="alert" style="color:red"> {{ $message }}</span>@enderror
        </p>

        <p>
            <label for="meta_description">Meta описание (до 320 знака)</label><br>
            <textarea id="meta_description" name="meta_description" maxlength="320" rows="3"
                      style="width:100%;max-width:560px;">{{ old('meta_description', $seo?->meta_description) }}</textarea>
            @error('meta_description')<span role="alert" style="color:red"> {{ $message }}</span>@enderror
        </p>

        <p>
            <label for="robots">Robots</label><br>
            <select id="robots" name="robots">
                @foreach ($robotsOptions as $opt)
                    <option value="{{ $opt }}"
                        {{ old('robots', $seo?->robots ?? 'index,follow') === $opt ? 'selected' : '' }}>
                        {{ $opt }}
                    </option>
                @endforeach
            </select>
        </p>

        <p>
            <label for="canonical_path">Canonical path (трябва да започва с /)</label><br>
            <input id="canonical_path" name="canonical_path" type="text" maxlength="512"
                   value="{{ old('canonical_path', $seo?->canonical_path) }}"
                   placeholder="/categories/example"
                   style="width:100%;max-width:560px;">
            @error('canonical_path')<span role="alert" style="color:red"> {{ $message }}</span>@enderror
        </p>

        <fieldset style="margin-top:1rem;border:1px solid #cbd5e1;padding:1rem;max-width:560px;">
            <legend>Open Graph</legend>

            <p>
                <label for="og_title">OG заглавие</label><br>
                <input id="og_title" name="og[title]" type="text" maxlength="120"
                       value="{{ old('og.title', $seo?->og['title'] ?? '') }}"
                       style="width:100%;">
            </p>
            <p>
                <label for="og_description">OG описание</label><br>
                <textarea id="og_description" name="og[description]" maxlength="300" rows="2"
                          style="width:100%;">{{ old('og.description', $seo?->og['description'] ?? '') }}</textarea>
            </p>
            <p>
                <label for="og_image_path">OG изображение (път)</label><br>
                <input id="og_image_path" name="og[image_path]" type="text" maxlength="512"
                       value="{{ old('og.image_path', $seo?->og['image_path'] ?? '') }}"
                       placeholder="/storage/media/og.jpg"
                       style="width:100%;">
                @error('og.image_path')<span role="alert" style="color:red"> {{ $message }}</span>@enderror
            </p>
        </fieldset>

        <p style="margin-top:1rem;">
            <button type="submit">Запази SEO</button>
        </p>
    </form>
@endsection
