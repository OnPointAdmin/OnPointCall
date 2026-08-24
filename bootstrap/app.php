<?php

use App\Http\Middleware\EnsureCanCall;
use App\Http\Middleware\SetCompanyContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('claims:expire')->everyMinute();
        $schedule->command('dashboard:email-digest')->everyMinute();
        $schedule->command('db:backup')->dailyAt('02:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return url('/admin/login');
            }

            return route('agent.login');
        });

        $middleware->web(append: [
            SetCompanyContext::class,
        ]);

        $middleware->alias([
            'can.call' => EnsureCanCall::class,
            'company.context' => SetCompanyContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
