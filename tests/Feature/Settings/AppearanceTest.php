<?php

use App\Models\User;

test('appearance settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('appearance.edit'));

    $response->assertOk();
});

test('guests are redirected to login from the appearance settings page', function () {
    $this->get(route('appearance.edit'))->assertRedirect(route('login'));
});

test('the appearance boot script loads before Flux scripts on every app page', function () {
    // flux.js captures window.Flux.applyAppearance as `t` at alpine:init time, then
    // replaces window.Flux with its own reactive object and uses that closure-captured
    // `t` to re-apply the appearance after a wire:navigate soft navigation. If this
    // script loads AFTER @fluxScripts (or not at all), flux.js falls back to a no-op
    // stub and dark mode never actually gets applied, with or without navigation.
    $user = User::factory()->admin()->create();

    // A plain Blade page (not a Livewire component), to also cover that @fluxScripts
    // is no longer gated behind `@isset($slot)`.
    $response = $this->actingAs($user)->get(route('machines.index'));
    $html = $response->assertOk()->getContent();

    $bootPos = strpos($html, 'window.Flux = {');
    $fluxScriptPos = strpos($html, '/flux/flux');

    expect($bootPos)->not->toBeFalse();
    expect($fluxScriptPos)->not->toBeFalse();
    expect($bootPos)->toBeLessThan($fluxScriptPos);
});
