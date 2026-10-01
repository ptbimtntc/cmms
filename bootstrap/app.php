<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\EnsureUserHasArea;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'role' => RoleMiddleware::class,
            'area' => EnsureUserHasArea::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Flips OPEN/IN_PROGRESS PM schedules to MISSED once their due_date
        // passes, even if nobody opens that PM's checklist — see
        // app/Console/Commands/MarkOverduePmSchedulesMissed.php. Needs the
        // server cron to actually call `php artisan schedule:run` every
        // minute, same as any Laravel scheduler.
        $schedule->command('pm-schedules:mark-overdue-missed')->dailyAt('00:05');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
