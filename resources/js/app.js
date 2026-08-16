import Alpine from 'alpinejs';
import Sortable from 'sortablejs';
import TomSelect from 'tom-select';
import { richTextEditor } from './editor/rich-text.js';

window.Alpine = Alpine;
window.Sortable = Sortable;
window.TomSelect = TomSelect;
window.richTextEditor = richTextEditor;

// Auto-init Sortable on any [data-sortable] list.
// The list must post the new order via the URL in data-sortable-url.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sortable]').forEach(el => {
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

    // Auto-init Tom Select on any [data-tom-select] element.
    document.querySelectorAll('[data-tom-select]').forEach(el => {
        const opts = {};
        if (el.dataset.tomSelectCreate) opts.create = true;
        if (el.multiple) opts.plugins = ['remove_button'];
        new TomSelect(el, opts);
    });
});

Alpine.start();
