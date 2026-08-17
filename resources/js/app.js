import Alpine from 'alpinejs';
import TomSelect from 'tom-select';
import { richTextEditor } from './editor/rich-text.js';

window.Alpine = Alpine;
window.TomSelect = TomSelect;
window.richTextEditor = richTextEditor;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tom-select]').forEach(el => {
        const opts = {};
        if (el.dataset.tomSelectCreate) opts.create = true;
        if (el.multiple) opts.plugins = ['remove_button'];
        new TomSelect(el, opts);
    });
});

Alpine.start();
