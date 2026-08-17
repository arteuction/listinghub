import Alpine from 'alpinejs';
import TomSelect from 'tom-select';

window.Alpine = Alpine;
window.TomSelect = TomSelect;
window.richTextEditor = function (opts) {
    return {
        editor: null,
        isFocused: false,
        async init() {
            const { richTextEditor } = await import('./editor/rich-text.js');
            const component = richTextEditor(opts);
            for (const [k, v] of Object.entries(component)) {
                if (k !== 'init') this[k] = v;
            }
            component.init.call(this);
        },
        destroy() { this.editor?.destroy(); },
    };
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tom-select]').forEach(el => {
        const opts = {};
        if (el.dataset.tomSelectCreate) opts.create = true;
        if (el.multiple) opts.plugins = ['remove_button'];
        new TomSelect(el, opts);
    });
});

Alpine.start();
