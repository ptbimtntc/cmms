/**
 * FreeDOMS offline-first — shared form-to-payload parser.
 *
 * Extracted from pmSave.js/pmChecklist.js (Task 5/6) into one shared
 * module for Task 8 to reuse rather than duplicating a third copy — see
 * Task 8 section 9 ("don't build a second dynamic-form system if the
 * existing one can be extended").
 *
 * Parses a <form>'s FormData into a nested structure mirroring how PHP
 * itself parses "name[index][field]" field names — using a plain OBJECT
 * at every level, deliberately never a JS Array.
 *
 * This matters whenever a dynamic list's rows can end up with gaps (PM
 * Save's measurements/problems/spareparts — see pm/edit.js's
 * removeProblem()/removeSparepart(), which just call `.remove()` on a row
 * without re-indexing the rest) — a JS Array with such a gap would
 * JSON.stringify the hole as `null`; a plain object with string keys
 * "0"/"2" instead round-trips through JSON exactly like PHP's own
 * form-urlencoded parsing already handles it (json_decode($json, true)
 * turns numeric-string object keys back into the same associative array
 * PHP would have built from the equivalent POST body). Oil Audit
 * Follow-up's own dynamic rows (unlike PM Save's) are always re-indexed
 * densely after every add/remove (see oil-audits/follow-up.js), so this
 * never actually produces a gap there — but using the identical parsing
 * strategy keeps every offline feature behaving predictably the same way.
 */
function parseFieldPath(key) {
    return key.split(/\[|\]/).filter((segment) => segment !== '');
}

export function parseFormToNestedObject(formData) {
    const result = {};

    for (const [key, value] of formData.entries()) {
        const path = parseFieldPath(key);
        let target = result;

        for (let i = 0; i < path.length - 1; i++) {
            const segment = path[i];

            if (typeof target[segment] !== 'object' || target[segment] === null) {
                target[segment] = {};
            }

            target = target[segment];
        }

        target[path[path.length - 1]] = value;
    }

    return result;
}

/**
 * Converts a dense (no-gaps) object-of-objects — as produced by
 * parseFormToNestedObject() for a numerically-keyed field — into a real
 * array, in key order. Only safe to use when the caller KNOWS the rows
 * are dense (e.g. a form whose JS always re-indexes after add/remove);
 * for a field that can legitimately have gaps, keep it as the object
 * parseFormToNestedObject() already produced instead (see PM Save).
 */
export function denseObjectToArray(obj) {
    return Object.keys(obj ?? {})
        .sort((a, b) => Number(a) - Number(b))
        .map((key) => obj[key]);
}
