async function bootFeatures() {
    if (document.querySelector('[data-sortable]:not([data-initialized="sortable"])')) {
        const { initSortable } = await import('./features/sortable.js');
        initSortable();
    }
    if (document.querySelector('[data-tom-select]:not([data-initialized="tom-select"])')) {
        const { initTomSelect } = await import('./features/tom-select.js');
        initTomSelect();
    }
}

document.addEventListener('DOMContentLoaded', bootFeatures);
document.addEventListener('livewire:navigated', bootFeatures);
