import { describe, expect, it } from 'vitest';
import { generateUuid } from '../uuid.js';

describe('generateUuid', () => {
    it('produces a well-formed v4 UUID string', () => {
        const id = generateUuid();

        expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
    });

    it('never produces the same value twice in a row', () => {
        const a = generateUuid();
        const b = generateUuid();

        expect(a).not.toBe(b);
    });

    it('is not just a timestamp or a small counter (Task 3 section 10)', () => {
        const id = generateUuid();

        expect(id.length).toBeGreaterThan(20);
        expect(Number.isFinite(Number(id))).toBe(false);
    });
});
