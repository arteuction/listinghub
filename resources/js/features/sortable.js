import Sortable from 'sortablejs';

/**
 * Initialize all [data-sortable] elements within a root.
 * Idempotent — skips elements with [data-initialized="sortable"].
 */
export function initSortable(root = document) {
    root.querySelectorAll('[data-sortable]:not([data-initialized="sortable"])').forEach(el => {
        el.setAttribute('data-initialized', 'sortable');
        const url = el.dataset.sortableUrl;
        const idAttr = el.dataset.sortableId ?? 'uuid';

        Sortable.create(el, {
            animation: 150,
            handle: '[data-drag-handle]',
            onEnd() {
                if (!url) return;
                const ids = Array.from(el.children).map(li => li.dataset[idAttr] ?? li.dataset.uuid);
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: JSON.stringify({ ids }),
                });
            },
        });
    });
}
