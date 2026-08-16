@extends('layouts.site')

@php
    $seoTitle = $seo?->meta_title ?: $page->title;
    $seoDesc  = $seo?->meta_description ?: '';
@endphp

@section('title', $seoTitle)
@section('meta_description', $seoDesc)

@if ($seo?->canonical_path)
    @section('canonical', url($seo->canonical_path))
@endif

@section('content')
    @if ($isPreview ?? false)
        <div role="status"
             style="background:#fef9c3;border:1px solid #fde047;padding:.6rem 1rem;margin-bottom:1.5rem;border-radius:.4rem;">
            Преглед (draft) — тази страница не е публично достъпна.
        </div>
    @endif

    <article>
        <h1 class="text-3xl font-semibold mb-6">{{ $page->title }}</h1>

        @forelse ($blocks as $block)
            @if ($block->block_type === \App\Enums\ContentBlockType::RichText)
                <div class="prose prose-slate max-w-none mb-8">
                    @tiptap($block->content['tiptap'] ?? [])
                </div>

            @elseif ($block->block_type === \App\Enums\ContentBlockType::Announcement)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-blue-800">
                    {{ $block->content['text'] ?? '' }}
                </div>

            @elseif ($block->block_type === \App\Enums\ContentBlockType::Hero)
                <div class="rounded-xl overflow-hidden mb-8 bg-slate-800 text-white p-10 text-center">
                    <h2 class="text-2xl font-bold mb-2">{{ $block->content['title'] ?? '' }}</h2>
                    @if (!empty($block->content['subtitle']))
                        <p class="text-slate-300 mb-4">{{ $block->content['subtitle'] }}</p>
                    @endif
                    @if (!empty($block->content['cta_text']) && !empty($block->content['cta_url']))
                        <a href="{{ $block->content['cta_url'] }}"
                           class="inline-block bg-white text-slate-900 px-6 py-2 rounded-lg font-medium hover:bg-slate-100">
                            {{ $block->content['cta_text'] }}
                        </a>
                    @endif
                </div>

            @elseif ($block->block_type === \App\Enums\ContentBlockType::Faq)
                <section class="mb-8" aria-label="Често задавани въпроси">
                    <h2 class="text-xl font-semibold mb-4">{{ $block->content['title'] ?? 'ЧЗВ' }}</h2>
                    @foreach ($block->content['items'] ?? [] as $faq)
                        <details class="border-b border-slate-200 py-3">
                            <summary class="font-medium cursor-pointer">{{ $faq['question'] ?? '' }}</summary>
                            <p class="mt-2 text-slate-600">{{ $faq['answer'] ?? '' }}</p>
                        </details>
                    @endforeach
                </section>

            @else
                {{-- Fallback: show block type label; specific renderers added per type --}}
                <div class="bg-slate-50 border border-dashed border-slate-300 rounded p-4 mb-4 text-sm text-slate-500">
                    [{{ $block->block_type->value }}]
                </div>
            @endif
        @empty
            <p class="text-slate-500">Тази страница все още няма съдържание.</p>
        @endforelse
    </article>
@endsection
