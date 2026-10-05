<?php

use App\Http\Middleware\EnsureUserHasArea;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            'role' => RoleMiddleware::class,
            'area' => EnsureUserHasArea::class,
        ]);

        // Authorize by role before route-model binding resolves, so a
        // forbidden role (e.g. view-only SUPERVISOR) gets 403 on a write
        // URL regardless of whether the target record exists.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: RoleMiddleware::class,
        );
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
