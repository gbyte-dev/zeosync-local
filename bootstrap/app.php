<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/ai.php'));
        },
    )
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

        $middleware->alias([
            'shopify.subscription' => \App\Http\Middleware\VerifyShopifySubscription::class,
            'subscription.check'   => \App\Http\Middleware\CheckSubscription::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'shopify/webhooks/*',
            'customers/data_request',
            'customers/redact',
            'shop/redact',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
