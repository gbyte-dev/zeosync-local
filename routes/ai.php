<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductSchemaController;
use Illuminate\Support\Facades\Cache;

Route::prefix('ai')
    ->name('ai.')
    ->group(function () {

        Route::post('/autofill', [ProductSchemaController::class, 'aiAutoFill'])
            ->name('autofill');

        Route::post('/generate-field', [ProductSchemaController::class, 'generateField'])
            ->name('generate-field');
    });

Route::get('/ai-test-token', function () {
    dd(Cache::get('ai_test_tokens'));
});
Route::get('/ai-test-token-reset', function () {

    Cache::forget('ai_test_tokens');

    return 'Reset Done';
});
