'use strict';
const form = document.querySelector('[data-device-form]');
if (form) {
    const establishment = form.querySelector('[data-establishment-select]');
    const traps = form.querySelector('[data-trap-select]');
    const filterTraps = () => {
        const selected = establishment.value;
        for (const option of traps.options) {
            if (!option.value) continue;
            option.hidden = option.dataset.establishment !== selected;
            if (option.hidden && option.selected) traps.value = '';
        }
        traps.disabled = selected === '';
    };
    establishment.addEventListener('change', filterTraps);
    filterTraps();
}
