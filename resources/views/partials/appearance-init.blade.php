{{--
    Anti-FOUC appearance boot for the app layout only.

    Reimplements Flux's own @fluxAppearance (vendor/livewire/flux/src/AssetManager.php)
    with one change: a FIRST VISIT defaults to 'light', not 'system'. Flux can't
    tell "never chosen" apart from "explicitly chose System" (both just mean the
    localStorage key is absent), so the 'flux.appearance.seen' sentinel marks
    that this boot has already run once on this device.

    Must load before @fluxScripts: flux.js captures whatever window.Flux.applyAppearance
    is at alpine:init time (as the variable it calls `t`), then REPLACES window.Flux with
    its own reactive appearance object (so window.Flux.applyAppearance stops existing after
    that point; don't call it from anywhere loaded after @fluxScripts).

    A wire:navigate soft navigation (e.g. between Settings tabs) morphs <html> against the
    freshly fetched page, which never has class="dark" server-side (the class only ever
    exists client-side), so the morph silently drops it. flux.js already re-applies it for
    us: inside that same alpine:init block it registers
    `document.addEventListener('livewire:navigated', () => t(e.appearance))`, calling the
    closure-captured `t` (this file's applyAppearance), not window.Flux. No extra listener
    needed here.

    Only included from layouts/app.blade.php, so .dark can never be applied to the
    guest/landing or auth/login layouts (they don't include this partial).
--}}
<style>
    :root.dark { color-scheme: dark; }
</style>
<script>
    window.Flux = {
        applyAppearance(appearance) {
            let applyDark = () => document.documentElement.classList.add('dark');
            let applyLight = () => document.documentElement.classList.remove('dark');

            if (appearance === 'system') {
                let media = window.matchMedia('(prefers-color-scheme: dark)');
                window.localStorage.removeItem('flux.appearance');
                media.matches ? applyDark() : applyLight();
            } else if (appearance === 'dark') {
                window.localStorage.setItem('flux.appearance', 'dark');
                applyDark();
            } else if (appearance === 'light') {
                window.localStorage.setItem('flux.appearance', 'light');
                applyLight();
            }
        }
    };

    let storedAppearance = window.localStorage.getItem('flux.appearance');

    if (storedAppearance === null && window.localStorage.getItem('flux.appearance.seen') === null) {
        storedAppearance = 'light';
    }

    window.localStorage.setItem('flux.appearance.seen', '1');
    window.Flux.applyAppearance(storedAppearance || 'system');
</script>
