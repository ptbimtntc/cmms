<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Today's Activity day-rollover needs no scheduled job: "active" is derived
// (ActiveActivityResolver only returns activities whose start date is today),
// so an activity from a previous day stops being active on its own at
// midnight without any record being mutated.
