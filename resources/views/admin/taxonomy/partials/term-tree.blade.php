<ul style="list-style:none;padding-left:{{ $depth * 1.5 }}rem;margin:0;">
    @foreach ($terms as $term)
        <li style="padding:.4rem 0;border-bottom:1px solid #e2e8f0;">
            <span style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                @if ($term->icon)
                    <span>{{ $term->icon }}</span>
                @endif
                <strong>{{ $term->name }}</strong>
                <small style="color:#64748b;">/{{ $term->slug }}</small>
                <small style="color:#94a3b8;">{{ $term->status->value }}</small>
                @if (isset($term->listings_count))
                    <small>{{ $term->listings_count }} обяви</small>
                @endif
                <a href="{{ route('admin.taxonomy.terms.edit', [$taxonomy, $term]) }}">Редактирай</a>
                ·
                <a href="{{ route('admin.taxonomy.terms.seo.edit', $term) }}">SEO</a>
                @unless ($term->children_count ?? $term->children?->count())
                    ·
                    <form method="POST" action="{{ route('admin.taxonomy.terms.destroy', [$taxonomy, $term]) }}"
                          style="display:inline" onsubmit="return confirm('Изтрий термина?')">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none;border:none;color:red;cursor:pointer;">Изтрий</button>
                    </form>
                @endunless
            </span>

            @if ($term->children && $term->children->isNotEmpty())
                @include('admin.taxonomy.partials.term-tree', [
                    'terms'    => $term->children,
                    'taxonomy' => $taxonomy,
                    'depth'    => $depth + 1,
                ])
            @endif
        </li>
    @endforeach
</ul>
