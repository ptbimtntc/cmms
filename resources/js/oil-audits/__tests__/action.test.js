// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initOilAuditActionInfiniteScroll } from '../action.js';

function renderActionPage({ hasMore = '1', nextPage = '2', loadMoreUrl = '/oil-audit-report/more' } = {}) {
    document.body.innerHTML = `
        <div id="oil-audit-action-mobile-list"
            data-load-more-url="${loadMoreUrl}"
            data-has-more="${hasMore}"
            data-next-page="${nextPage}">
            <article data-audit-id="1">existing card</article>
        </div>
        <div>
            <div id="oil-audit-action-mobile-sentinel"></div>
            <p id="oil-audit-action-mobile-loading" class="hidden">Memuat data…</p>
            <p id="oil-audit-action-mobile-end" class="hidden">Semua data sudah ditampilkan.</p>
            <div id="oil-audit-action-mobile-error" class="hidden">
                Gagal memuat data berikutnya.
                <button type="button" id="oil-audit-action-mobile-retry">Coba lagi</button>
            </div>
        </div>`;
}

const list = () => document.getElementById('oil-audit-action-mobile-list');
const loadingEl = () => document.getElementById('oil-audit-action-mobile-loading');
const endEl = () => document.getElementById('oil-audit-action-mobile-end');
const errorEl = () => document.getElementById('oil-audit-action-mobile-error');
const retryButton = () => document.getElementById('oil-audit-action-mobile-retry');

beforeEach(() => {
    vi.stubGlobal('IntersectionObserver', class {
        observe() {}

        disconnect() {}
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('Oil Audit Action — mobile infinite scroll', () => {
    it('does nothing when the mobile list container is absent (e.g. empty-result page)', () => {
        document.body.innerHTML = '<p>Tidak ada audit yang sesuai dengan filter.</p>';

        expect(() => initOilAuditActionInfiniteScroll()).not.toThrow();
    });

    it('fetches the next page with the page number from data-next-page and appends the returned html', async () => {
        renderActionPage();
        const fetchSpy = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ html: '<article data-audit-id="2">new card</article>', has_more: true, next_page: 3 }),
        });
        vi.stubGlobal('fetch', fetchSpy);

        const { loadNextPage } = initOilAuditActionInfiniteScroll();
        await loadNextPage();

        expect(fetchSpy).toHaveBeenCalledTimes(1);
        const requestedUrl = new URL(fetchSpy.mock.calls[0][0]);
        expect(requestedUrl.pathname).toBe('/oil-audit-report/more');
        expect(requestedUrl.searchParams.get('page')).toBe('2');

        expect(list().innerHTML).toContain('existing card');
        expect(list().innerHTML).toContain('new card');
        expect(endEl().classList.contains('hidden')).toBe(true);
    });

    it('advances to the next_page the server returned, so a third scroll asks for page 3', async () => {
        renderActionPage();
        const fetchSpy = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ html: '<article>x</article>', has_more: true, next_page: 3 }),
        });
        vi.stubGlobal('fetch', fetchSpy);

        const { loadNextPage } = initOilAuditActionInfiniteScroll();
        await loadNextPage();
        await loadNextPage();

        expect(fetchSpy).toHaveBeenCalledTimes(2);
        expect(new URL(fetchSpy.mock.calls[1][0]).searchParams.get('page')).toBe('3');
    });

    it('shows the "all data shown" message and stops requesting once has_more is false', async () => {
        renderActionPage();
        const fetchSpy = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ html: '<article>last</article>', has_more: false, next_page: null }),
        });
        vi.stubGlobal('fetch', fetchSpy);

        const { loadNextPage } = initOilAuditActionInfiniteScroll();
        await loadNextPage();

        expect(endEl().classList.contains('hidden')).toBe(false);

        await loadNextPage(); // must be a no-op now
        expect(fetchSpy).toHaveBeenCalledTimes(1);
    });

    it('shows the "all data shown" message immediately when the first page already had no more pages', () => {
        renderActionPage({ hasMore: '0', nextPage: '2' });

        initOilAuditActionInfiniteScroll();

        expect(endEl().classList.contains('hidden')).toBe(false);
    });

    it('ignores overlapping triggers while a request is already in flight (no duplicate fetch)', async () => {
        renderActionPage();
        let resolveFetch;
        const fetchSpy = vi.fn().mockReturnValue(new Promise((resolve) => { resolveFetch = resolve; }));
        vi.stubGlobal('fetch', fetchSpy);

        const { loadNextPage } = initOilAuditActionInfiniteScroll();
        const first = loadNextPage();
        const second = loadNextPage(); // fired while the first is still pending

        expect(fetchSpy).toHaveBeenCalledTimes(1);

        resolveFetch({ ok: true, json: async () => ({ html: '', has_more: true, next_page: 3 }) });
        await Promise.all([first, second]);

        expect(fetchSpy).toHaveBeenCalledTimes(1);
    });

    it('shows the loading indicator while the request is pending and hides it afterwards', async () => {
        renderActionPage();
        let resolveFetch;
        vi.stubGlobal('fetch', vi.fn().mockReturnValue(new Promise((resolve) => { resolveFetch = resolve; })));

        const { loadNextPage } = initOilAuditActionInfiniteScroll();
        const pending = loadNextPage();

        expect(loadingEl().classList.contains('hidden')).toBe(false);

        resolveFetch({ ok: true, json: async () => ({ html: '', has_more: false, next_page: null }) });
        await pending;

        expect(loadingEl().classList.contains('hidden')).toBe(true);
    });

    it('shows an error and offers retry when the request fails, and clears it on a successful retry', async () => {
        renderActionPage();
        const fetchSpy = vi.fn()
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockResolvedValueOnce({ ok: true, json: async () => ({ html: '<article>recovered</article>', has_more: true, next_page: 3 }) });
        vi.stubGlobal('fetch', fetchSpy);

        const { loadNextPage } = initOilAuditActionInfiniteScroll();
        await loadNextPage();

        expect(errorEl().classList.contains('hidden')).toBe(false);
        expect(list().innerHTML).not.toContain('recovered');

        retryButton().click();
        await vi.waitFor(() => expect(list().innerHTML).toContain('recovered'));
        expect(errorEl().classList.contains('hidden')).toBe(true);
    });

    it('treats a non-ok HTTP response the same as a network failure', async () => {
        renderActionPage();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500 }));

        const { loadNextPage } = initOilAuditActionInfiniteScroll();
        await loadNextPage();

        expect(errorEl().classList.contains('hidden')).toBe(false);
    });

    it('registers an IntersectionObserver on the sentinel and loads more once it intersects', async () => {
        renderActionPage();
        let capturedCallback;
        const observeSpy = vi.fn();
        vi.stubGlobal('IntersectionObserver', class {
            constructor(callback) {
                capturedCallback = callback;
            }

            observe(...args) {
                observeSpy(...args);
            }

            disconnect() {}
        });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ html: '<article>observed</article>', has_more: true, next_page: 3 }),
        }));

        initOilAuditActionInfiniteScroll();

        expect(observeSpy).toHaveBeenCalledWith(document.getElementById('oil-audit-action-mobile-sentinel'));

        capturedCallback([{ isIntersecting: true }]);
        await vi.waitFor(() => expect(list().innerHTML).toContain('observed'));
    });
});
