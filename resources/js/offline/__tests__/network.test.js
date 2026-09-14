// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { isOnline, onNetworkChange } from '../network.js';

describe('isOnline', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('reflects navigator.onLine when it is true', () => {
        vi.stubGlobal('navigator', { onLine: true });

        expect(isOnline()).toBe(true);
    });

    it('reflects navigator.onLine when it is false', () => {
        vi.stubGlobal('navigator', { onLine: false });

        expect(isOnline()).toBe(false);
    });
});

describe('onNetworkChange', () => {
    it('calls back with true on the "online" event and false on "offline"', () => {
        const calls = [];
        const unsubscribe = onNetworkChange((online) => calls.push(online));

        window.dispatchEvent(new Event('online'));
        window.dispatchEvent(new Event('offline'));

        expect(calls).toEqual([true, false]);

        unsubscribe();
    });

    it('the returned unsubscribe function stops further callbacks', () => {
        const calls = [];
        const unsubscribe = onNetworkChange((online) => calls.push(online));

        unsubscribe();
        window.dispatchEvent(new Event('online'));

        expect(calls).toEqual([]);
    });
});
