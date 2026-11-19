/**
 * PM Status Board — "load next month" button.
 *
 * Fetches one future month (rendered by the server as row markup) and
 * appends it to the desktop table and the mobile cards without reloading the
 * page. The server says whether another month can still be loaded.
 */

import { applyOverrides } from './override';

function appendHtml(container, html) {
    if (!container || !html) {
        return;
    }

    const template = document.createElement('template');
    template.innerHTML = html;
    const nodes = Array.from(template.content.children);

    container.append(...nodes);

    return nodes;
}

async function loadNextMonth(button) {
    const errorEl = document.querySelector('[data-pm-month-error]');
    const label = button.textContent;

    button.disabled = true;
    button.textContent = 'Memuat...';
    errorEl?.classList.add('hidden');

    try {
        const response = await fetch(`${button.dataset.url}?offset=${button.dataset.offset}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        // A previously empty board shows a placeholder row/card — drop it.
        document.querySelectorAll('[data-pm-empty]').forEach((el) => el.remove());

        const added = [
            ...(appendHtml(document.querySelector('[data-pm-rows-desktop]'), data.desktop) || []),
            ...(appendHtml(document.querySelector('[data-pm-rows-mobile]'), data.mobile) || []),
        ];

        added.forEach((node) => applyOverrides(node));

        if (data.has_more) {
            button.dataset.offset = data.next_offset;
            button.textContent = `Klik disini untuk memuat mesin bulan ${data.next_label}`;
            button.disabled = false;
        } else {
            button.remove();
            document.querySelector('[data-pm-month-done]')?.classList.remove('hidden');
        }
    } catch (e) {
        button.textContent = label;
        button.disabled = false;
        errorEl?.classList.remove('hidden');
    }
}

function initLoadMonth() {
    const button = document.querySelector('[data-pm-month-next]');

    if (button) {
        button.addEventListener('click', () => loadNextMonth(button));
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoadMonth);
    } else {
        initLoadMonth();
    }
}

export { initLoadMonth, loadNextMonth };
