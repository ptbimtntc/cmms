// @vitest-environment jsdom
import { beforeEach, describe, expect, it } from 'vitest';
import { currentUserId } from '../scope.js';

describe('currentUserId', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
    });

    it('reads the user id from the <meta name="app-user-id"> tag', () => {
        const meta = document.createElement('meta');

        meta.setAttribute('name', 'app-user-id');
        meta.setAttribute('content', '42');
        document.head.appendChild(meta);

        expect(currentUserId()).toBe('42');
    });

    it('returns null when the meta tag is absent (e.g. a guest page)', () => {
        expect(currentUserId()).toBeNull();
    });

    it('returns null when the meta tag content is empty (auth()->id() was null)', () => {
        const meta = document.createElement('meta');

        meta.setAttribute('name', 'app-user-id');
        meta.setAttribute('content', '');
        document.head.appendChild(meta);

        expect(currentUserId()).toBeNull();
    });
});
