/**
 * Oil Audit Action — mobile infinite scroll.
 *
 * The desktop table keeps Laravel's numbered pagination untouched
 * ($audits->links() in action.blade.php). This only drives the mobile card
 * list (#oil-audit-action-mobile-list), which starts with the same first
 * 20 audits the server already rendered and fetches subsequent pages from
 * OilAuditController::actionMore() (GET /oil-audit-report/more) as the user
 * scrolls near the bottom.
 *
 * All filtering/search/sorting is done server-side by actionMore() via the
 * exact same filteredAuditQuery() the initial page used — this file never
 * filters or sorts anything client-side, it only appends whatever HTML the
 * server sends back. Changing a filter is a normal GET form submit (full
 * page reload), so there is no client-side "switch filters" state to
 * manage here.
 */

function initOilAuditActionInfiniteScroll() {
    const list = document.getElementById('oil-audit-action-mobile-list');

    if (!list) {
        return;
    }

    const sentinel = document.getElementById('oil-audit-action-mobile-sentinel');
    const loadingEl = document.getElementById('oil-audit-action-mobile-loading');
    const endEl = document.getElementById('oil-audit-action-mobile-end');
    const errorEl = document.getElementById('oil-audit-action-mobile-error');
    const retryButton = document.getElementById('oil-audit-action-mobile-retry');

    let hasMore = list.dataset.hasMore === '1';
    let nextPage = Number(list.dataset.nextPage) || 2;
    let loading = false;

    function setHidden(el, hidden) {
        if (el) {
            el.classList.toggle('hidden', hidden);
        }
    }

    async function loadNextPage() {
        if (loading || !hasMore) {
            return;
        }

        loading = true;
        setHidden(errorEl, true);
        setHidden(loadingEl, false);

        try {
            const url = new URL(list.dataset.loadMoreUrl, window.location.origin);
            url.searchParams.set('page', String(nextPage));

            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error('Request failed with status ' + response.status);
            }

            const data = await response.json();

            if (data.html) {
                list.insertAdjacentHTML('beforeend', data.html);
            }

            hasMore = Boolean(data.has_more);
            nextPage = data.next_page || nextPage + 1;

            setHidden(endEl, hasMore);
        } catch (error) {
            setHidden(errorEl, false);
        } finally {
            loading = false;
            setHidden(loadingEl, true);
        }
    }

    if (!hasMore) {
        setHidden(endEl, false);
    }

    retryButton?.addEventListener('click', loadNextPage);

    if (sentinel && typeof IntersectionObserver !== 'undefined') {
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                loadNextPage();
            }
        }, { rootMargin: '200px' });

        observer.observe(sentinel);
    }

    return { loadNextPage };
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOilAuditActionInfiniteScroll);
    } else {
        initOilAuditActionInfiniteScroll();
    }
}

export { initOilAuditActionInfiniteScroll };
