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

for (const printButton of document.querySelectorAll('[data-print-page]')) {
    printButton.addEventListener('click', () => window.print());
}

const notificationStatus = document.querySelector('[data-notification-status]');
if (notificationStatus) {
    const count = notificationStatus.querySelector('[data-notification-count]');
    const empty = notificationStatus.querySelector('[data-notification-empty]');
    const label = notificationStatus.querySelector('[data-notification-label]');

    const applyNotificationSummary = (summary) => {
        const total = Number.isFinite(Number(summary.total)) ? Math.max(0, Number(summary.total)) : 0;
        const critical = Number.isFinite(Number(summary.critical)) ? Math.max(0, Number(summary.critical)) : 0;
        const description = total === 0
            ? 'No unresolved alerts'
            : `${total} unresolved alert${total === 1 ? '' : 's'}`;

        count.textContent = total > 99 ? '99+' : String(total);
        count.hidden = total === 0;
        empty.hidden = total > 0;
        label.textContent = description;
        notificationStatus.title = description;
        notificationStatus.setAttribute('aria-label', description);
        notificationStatus.classList.toggle('has-critical', critical > 0);
    };

    const refreshNotificationSummary = async () => {
        if (document.visibilityState !== 'visible') return;
        try {
            const response = await fetch(notificationStatus.dataset.summaryUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (payload.success && payload.data) applyNotificationSummary(payload.data);
        } catch {
            // Keep the server-rendered count when a refresh is temporarily unavailable.
        }
    };

    refreshNotificationSummary();
    window.setInterval(refreshNotificationSummary, 15000);
    document.addEventListener('visibilitychange', refreshNotificationSummary);
}
