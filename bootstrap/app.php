<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {

        $schedule->command('stores:check-status')
            ->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->redirectGuestsTo(function (Request $request) {

            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return route('crm.entry');
        });

        $middleware->web(append: [
            \App\Http\Middleware\ResolveActiveShop::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            '*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
