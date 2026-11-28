<?php

use App\Models\User;

test('dashboard, today activity, and pm schedules all carry the same appearance boot and flux scripts', function () {
    $user = User::factory()->admin()->create();

    $routes = [
        'dashboard' => route('dashboard'),
        'today-activity.index' => route('today-activity.index'),
        'pm-schedules.index' => route('pm-schedules.index'),
    ];

    $bootSnippet = "window.Flux = {";

    foreach ($routes as $name => $url) {
        $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

        $bootPos = strpos($html, $bootSnippet);
        $fluxScriptPos = strpos($html, '/flux/flux');

        expect($bootPos)->not->toBeFalse("Missing appearance boot script on {$name}");
        expect($fluxScriptPos)->not->toBeFalse("Missing @fluxScripts on {$name}");
        expect($bootPos)->toBeLessThan($fluxScriptPos, "Boot script is not before @fluxScripts on {$name}");
    }
});
