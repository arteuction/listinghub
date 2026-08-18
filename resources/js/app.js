import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.richTextEditor = function (opts) {
    return {
        editor: null,
        isFocused: false,
        async init() {
            const { richTextEditor } = await import('./features/rich-text.js');
            const component = richTextEditor(opts);
            for (const [k, v] of Object.entries(component)) {
                if (k !== 'init') this[k] = v;
            }
            component.init.call(this);
        },
        destroy() { this.editor?.destroy(); },
    };
};

async function bootFeatures() {
    if (document.querySelector('[data-tom-select]:not([data-initialized="tom-select"])')) {
        const { initTomSelect } = await import('./features/tom-select.js');
        initTomSelect();
    }
}

document.addEventListener('DOMContentLoaded', bootFeatures);
document.addEventListener('livewire:navigated', bootFeatures);

Alpine.start();
