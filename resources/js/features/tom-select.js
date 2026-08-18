import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.default.css';

/**
 * Initialize all [data-tom-select] elements within a root.
 * Idempotent — skips elements with [data-initialized="tom-select"].
 */
export function initTomSelect(root = document) {
    root.querySelectorAll('[data-tom-select]:not([data-initialized="tom-select"])').forEach(el => {
        el.setAttribute('data-initialized', 'tom-select');
        const opts = {};
        if (el.dataset.tomSelectCreate) opts.create = true;
        if (el.multiple) opts.plugins = ['remove_button'];
        new TomSelect(el, opts);
    });
}
