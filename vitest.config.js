import { defineConfig } from 'vite';

/**
 * FreeDOMS offline-first — JS test runner config (Phase 1, Task 3).
 *
 * Kept as its own file rather than extending vite.config.js: the app's
 * Vite config wires up laravel-vite-plugin (expects a running Laravel app
 * / manifest) which Vitest's Node test environment has no business
 * touching. Vitest is the natural choice here since Vite is already the
 * project's build tool — no other JS test tooling existed before this
 * task (see the Task 3 report for what was audited).
 */
export default defineConfig({
    test: {
        environment: 'node',
        setupFiles: ['./resources/js/offline/__tests__/setup.js'],
        include: ['resources/js/**/__tests__/**/*.test.js'],
    },
});
