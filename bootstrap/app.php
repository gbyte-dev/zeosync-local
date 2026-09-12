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
    ->withCommands([
        __DIR__ . '/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->redirectGuestsTo(function (Request $request) {

            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return route('crm.entry');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if (auth('admin')->check() || $request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            return route('dashboard');
        });
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->web(append: [
            \App\Http\Middleware\VerifyShopifyAuthentication::class,
            \App\Http\Middleware\ResolveActiveShop::class,
        ]);

        $middleware->alias([
            'guest'                => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'shopify.auth'         => \App\Http\Middleware\VerifyShopifyAuthentication::class,
            'shopify.subscription' => \App\Http\Middleware\VerifyShopifySubscription::class,
            'subscription.check'   => \App\Http\Middleware\CheckSubscription::class,
            'ip.rate'              => \App\Http\Middleware\EnforceIpAndRateLimit::class,
            'admin.verify'         => \App\Http\Middleware\VerifyAdminRequest::class,
            'admin.auth'           => \App\Http\Middleware\EnsureAdminAuthenticated::class,
        ]);

        // $middleware->validateCsrfTokens(except: [
        //     'webhooks/*',
        //     'shopify/webhooks/*',
        //     'customers/data_request',
        //     'customers/redact',
        //     'shop/redact',
        // ]);

       
        $middleware->validateCsrfTokens(except: [
            '*',
            '!contacts',
            '!/contact',
            'contact' 
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
