// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import { denseObjectToArray, parseFormToNestedObject } from '../formParser.js';

describe('parseFormToNestedObject', () => {
    it('parses flat fields as top-level keys', () => {
        const fd = new FormData();

        fd.append('action_taken', 'Ganti seal');

        expect(parseFormToNestedObject(fd)).toEqual({ action_taken: 'Ganti seal' });
    });

    it('parses two levels of bracketed nesting', () => {
        const fd = new FormData();

        fd.append('problems[0][problem]', 'Bocor Oli');
        fd.append('problems[1][problem]', 'Baut Kendor');

        expect(parseFormToNestedObject(fd)).toEqual({
            problems: { 0: { problem: 'Bocor Oli' }, 1: { problem: 'Baut Kendor' } },
        });
    });

    it('parses three levels of bracketed nesting (problem -> findings -> finding)', () => {
        const fd = new FormData();

        fd.append('problems[0][problem]', 'Bocor Oli');
        fd.append('problems[0][findings][0][finding]', 'Kapstan 1');
        fd.append('problems[0][findings][1][finding]', 'Kapstan 2');

        const parsed = parseFormToNestedObject(fd);

        expect(parsed.problems[0].problem).toBe('Bocor Oli');
        expect(parsed.problems[0].findings).toEqual({
            0: { finding: 'Kapstan 1' },
            1: { finding: 'Kapstan 2' },
        });
    });

    it('never produces a JS Array — a gap round-trips through JSON safely', () => {
        const fd = new FormData();

        fd.append('problems[0][problem]', 'A');
        fd.append('problems[2][problem]', 'C');

        const parsed = parseFormToNestedObject(fd);

        expect(Array.isArray(parsed.problems)).toBe(false);
        expect(JSON.parse(JSON.stringify(parsed)).problems).toEqual({ 0: { problem: 'A' }, 2: { problem: 'C' } });
    });
});

describe('denseObjectToArray', () => {
    it('converts a dense numeric-keyed object into an array, in key order', () => {
        expect(denseObjectToArray({ 0: 'a', 1: 'b', 2: 'c' })).toEqual(['a', 'b', 'c']);
    });

    it('sorts numerically, not lexically (so "2" sorts before "10")', () => {
        expect(denseObjectToArray({ 10: 'ten', 2: 'two', 0: 'zero' })).toEqual(['zero', 'two', 'ten']);
    });

    it('returns an empty array for null/undefined/empty input', () => {
        expect(denseObjectToArray(undefined)).toEqual([]);
        expect(denseObjectToArray(null)).toEqual([]);
        expect(denseObjectToArray({})).toEqual([]);
    });
});
