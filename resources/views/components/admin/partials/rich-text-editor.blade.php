{{--
    Rich-text editor component (Tiptap / Alpine.js).

    Parameters:
      $inputName   — the hidden <input> name that carries the Tiptap JSON (e.g. "content[tiptap]")
      $inputId     — id attribute for the hidden input
      $value       — current Tiptap JSON doc (array or null)
      $placeholder — optional placeholder text
      $label       — accessible label shown above the toolbar
--}}
@props([
    'inputName',
    'inputId',
    'value'       => null,
    'placeholder' => '',
    'label'       => __('Content'),
])

@php
    $initialJson = $value ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : 'null';
@endphp

<div
    x-data="richTextEditor({
        hiddenInputId: {{ Js::from($inputId) }},
        initialJson:   {{ $initialJson }},
        placeholder:   {{ Js::from($placeholder) }},
    })"
    x-init="init()"
    x-on:destroy="destroy()"
    class="rich-text-editor"
>
    {{-- Accessible label --}}
    <p class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</p>

    {{-- Toolbar --}}
    <div
        role="toolbar"
        aria-label="{{ __('Formatting') }}"
        class="flex flex-wrap items-center gap-1 rounded-t-lg border border-b-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-2 py-1"
    >
        {{-- Paragraph / Heading select --}}
        <select
            aria-label="{{ __('Text style') }}"
            class="text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 px-1 py-0.5 focus:ring-2 focus:ring-indigo-500"
            x-on:change="
                const v = $event.target.value;
                if (v === 'p')       editor?.chain().focus().setParagraph().run();
                else if (v === 'h2') setHeading(2);
                else if (v === 'h3') setHeading(3);
            "
            x-effect="
                $el.value = isActive('heading', {level:2}) ? 'h2'
                          : isActive('heading', {level:3}) ? 'h3'
                          : 'p'
            "
        >
            <option value="p">{{ __('Paragraph') }}</option>
            <option value="h2">{{ __('Heading 2') }}</option>
            <option value="h3">{{ __('Heading 3') }}</option>
        </select>

        <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>

        @foreach([
            ['cmd' => 'toggleBold',       'mark' => 'bold',       'icon' => 'B',  'label' => 'Bold',       'class' => 'font-bold'],
            ['cmd' => 'toggleItalic',     'mark' => 'italic',     'icon' => 'I',  'label' => 'Italic',     'class' => 'italic'],
            ['cmd' => 'toggleStrike',     'mark' => 'strike',     'icon' => 'S̶',  'label' => 'Strikethrough', 'class' => 'line-through'],
        ] as $btn)
        <button
            type="button"
            aria-label="{{ __($btn['label']) }}"
            x-on:click="{{ $btn['cmd'] }}()"
            x-bind:aria-pressed="isActive({{ Js::from($btn['mark']) }})"
            x-bind:class="isActive({{ Js::from($btn['mark']) }}) ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
            class="toolbar-btn w-7 h-7 rounded flex items-center justify-center text-sm {{ $btn['class'] }}"
        >{{ $btn['icon'] }}</button>
        @endforeach

        <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>

        <button type="button" aria-label="{{ __('Link') }}"
            x-on:click="setLink()"
            x-bind:class="isActive('link') ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
            class="toolbar-btn w-7 h-7 rounded flex items-center justify-center text-sm"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
        </button>

        <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>

        <button type="button" aria-label="{{ __('Bullet list') }}"
            x-on:click="toggleBulletList()"
            x-bind:class="isActive('bulletList') ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
            class="toolbar-btn w-7 h-7 rounded flex items-center justify-center text-sm"
        >&#8226;&#8212;</button>

        <button type="button" aria-label="{{ __('Numbered list') }}"
            x-on:click="toggleOrderedList()"
            x-bind:class="isActive('orderedList') ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
            class="toolbar-btn w-7 h-7 rounded flex items-center justify-center text-sm"
        >1.</button>

        <button type="button" aria-label="{{ __('Blockquote') }}"
            x-on:click="toggleBlockquote()"
            x-bind:class="isActive('blockquote') ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
            class="toolbar-btn w-7 h-7 rounded flex items-center justify-center text-sm"
        >&#8220;&#8221;</button>

        <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>

        <button type="button" aria-label="{{ __('Undo') }}"
            x-on:click="undo()"
            x-bind:disabled="!canUndo()"
            class="toolbar-btn w-7 h-7 rounded flex items-center justify-center text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-40"
        >&#8617;</button>

        <button type="button" aria-label="{{ __('Redo') }}"
            x-on:click="redo()"
            x-bind:disabled="!canRedo()"
            class="toolbar-btn w-7 h-7 rounded flex items-center justify-center text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-40"
        >&#8618;</button>
    </div>

    {{-- Editor surface --}}
    <div
        x-ref="editorContent"
        x-bind:class="isFocused ? 'ring-2 ring-indigo-500' : ''"
        class="min-h-48 rounded-b-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 overflow-y-auto"
    ></div>

    {{-- Hidden input carrying the Tiptap JSON to the form --}}
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}">
</div>
