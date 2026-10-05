/**
 * Pull-to-refresh for the mobile web app / PWA.
 *
 * The app layout locks <body> (overflow-hidden) and scrolls inside <main>,
 * so the browser's native pull-to-refresh never fires. This adds the same
 * gesture globally, with no dependency: it only arms when <main> is at
 * scrollTop 0, on a touch device with a narrow viewport, and refreshes with
 * a plain location.reload() (navigations are network-first in sw.js, so the
 * reload always fetches fresh data and offline fallback is untouched).
 */
const THRESHOLD = 70; // px of (damped) pull needed to trigger a refresh
const MAX_PULL = 110;
const DAMPING = 0.5;
const MOBILE_QUERY = '(max-width: 1023px) and (pointer: coarse)';

// Touches starting inside these never start a pull (form controls, custom
// dropdowns, drawers) so their own gestures keep working.
const IGNORED_SELECTOR = [
    'input', 'textarea', 'select', '[contenteditable="true"]',
    '[role="dialog"]', '[role="listbox"]', '.ts-wrapper', '.ts-dropdown', 'aside',
].join(',');

function init() {
    const scroller = document.querySelector('main');
    if (!scroller || !window.matchMedia) {
        return;
    }

    const mq = window.matchMedia(MOBILE_QUERY);
    const indicator = createIndicator();
    document.body.appendChild(indicator.el);

    let startY = 0;
    let startX = 0;
    let pull = 0;
    let tracking = false; // finger is down on an eligible start
    let pulling = false; // gesture confirmed as a vertical downward pull
    let refreshing = false;

    const overlayOpen = () => {
        if (window.Alpine?.store?.('sidebar')?.open) {
            return true;
        }
        return [...document.querySelectorAll('.fixed.inset-0')].some(
            (el) => el.offsetWidth > 0 && el.offsetHeight > 0 && !el.contains(scroller),
        );
    };

    // A nested scroller (e.g. a wide table) that is scrolled itself must
    // keep its own scrolling instead of starting a pull.
    const insideScrolledContainer = (target) => {
        for (let el = target; el && el !== scroller; el = el.parentElement) {
            if (el.scrollTop > 0 || el.scrollLeft > 0) {
                return true;
            }
        }
        return false;
    };

    const reset = () => {
        tracking = false;
        pulling = false;
        pull = 0;
    };

    scroller.addEventListener('touchstart', (e) => {
        if (refreshing || !mq.matches || e.touches.length !== 1) {
            return reset();
        }
        if (scroller.scrollTop > 0 || e.target.closest(IGNORED_SELECTOR) || overlayOpen()
            || insideScrolledContainer(e.target)) {
            return reset();
        }
        tracking = true;
        pulling = false;
        startY = e.touches[0].clientY;
        startX = e.touches[0].clientX;
    }, { passive: true });

    scroller.addEventListener('touchmove', (e) => {
        if (!tracking || refreshing) {
            return;
        }
        const dy = e.touches[0].clientY - startY;
        const dx = e.touches[0].clientX - startX;

        if (!pulling) {
            if (scroller.scrollTop > 0 || dy < 0) {
                return reset(); // normal scroll / upward swipe
            }
            if (Math.abs(dx) > Math.abs(dy)) {
                return reset(); // horizontal gesture
            }
            if (dy < 8) {
                return; // not yet a deliberate pull
            }
            pulling = true;
        }

        pull = Math.min(MAX_PULL, dy * DAMPING);
        if (e.cancelable) {
            e.preventDefault(); // stop native overscroll while pulling
        }
        indicator.show(pull, pull >= THRESHOLD ? 'release' : 'pull');
    }, { passive: false });

    const end = () => {
        if (pulling && pull >= THRESHOLD && !refreshing) {
            refreshing = true;
            indicator.show(THRESHOLD, 'refreshing');
            window.location.reload();
        } else {
            indicator.hide();
        }
        reset();
    };
    scroller.addEventListener('touchend', end, { passive: true });
    scroller.addEventListener('touchcancel', () => { indicator.hide(); reset(); }, { passive: true });

    // Restored from the back/forward cache: clear any stale "Refreshing…".
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) {
            refreshing = false;
            indicator.hide();
        }
    });
}

function createIndicator() {
    const el = document.createElement('div');
    el.setAttribute('aria-live', 'polite');
    el.style.cssText = [
        'position:fixed', 'top:0', 'left:50%', 'z-index:40', 'pointer-events:none',
        'transform:translate(-50%,-100%)', 'opacity:0', 'transition:transform .2s ease,opacity .2s ease',
        'padding:6px 14px', 'border-radius:9999px', 'font-size:12px', 'font-weight:500',
        'background:var(--color-surface,#fff)', 'color:var(--color-text,#334155)',
        'border:1px solid var(--color-border,#e2e8f0)', 'box-shadow:0 1px 3px rgba(0,0,0,.12)',
        'white-space:nowrap',
    ].join(';');

    const labels = {
        pull: '↓ Pull to refresh',
        release: '↻ Release to refresh',
        refreshing: '↻ Refreshing...',
    };

    return {
        el,
        show(distance, state) {
            el.textContent = labels[state];
            el.style.transition = state === 'refreshing' ? 'transform .2s ease' : 'none';
            el.style.opacity = '1';
            el.style.transform = `translate(-50%, ${Math.round(distance) - 8}px)`;
        },
        hide() {
            el.style.transition = 'transform .2s ease,opacity .2s ease';
            el.style.opacity = '0';
            el.style.transform = 'translate(-50%,-100%)';
        },
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
