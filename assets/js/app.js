'use strict';

const passwordToggle = document.querySelector('[data-password-toggle]');
passwordToggle?.addEventListener('click', () => {
    const input = document.getElementById('password');
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    passwordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    passwordToggle.setAttribute('aria-pressed', String(visible));
});

const menuToggle = document.querySelector('[data-menu-toggle]');
function setNavigation(open) {
    document.body.classList.toggle('navigation-open', open);
    menuToggle?.setAttribute('aria-expanded', String(open));
    menuToggle?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    if (open) document.querySelector('#sidebar a')?.focus();
    else menuToggle?.focus();
}
menuToggle?.addEventListener('click', () => {
    setNavigation(!document.body.classList.contains('navigation-open'));
});
document.querySelector('[data-menu-close]')?.addEventListener('click', () => setNavigation(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('navigation-open')) {
        setNavigation(false);
    }
    if (event.key === 'Tab' && document.body.classList.contains('navigation-open')) {
        const links = document.querySelectorAll('#sidebar a');
        const first = links[0];
        const last = links[links.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
});

window.matchMedia('(max-width: 760px)').addEventListener('change', (event) => {
    if (!event.matches) {
        document.body.classList.remove('navigation-open');
        menuToggle?.setAttribute('aria-expanded', 'false');
    }
});

for (const form of document.querySelectorAll('form[data-confirm]')) {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
}
