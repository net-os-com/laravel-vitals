<?php

use Illuminate\Support\Facades\Route;
use NetOs\Vitals\Http\Controllers\CheckController;
use NetOs\Vitals\Http\Controllers\DeleteMuteController;
use NetOs\Vitals\Http\Controllers\OverviewController;
use NetOs\Vitals\Http\Controllers\RunAllController;
use NetOs\Vitals\Http\Controllers\RunCheckController;
use NetOs\Vitals\Http\Controllers\RunTenantController;
use NetOs\Vitals\Http\Controllers\StatusController;
use NetOs\Vitals\Http\Controllers\StoreMuteController;
use NetOs\Vitals\Http\Controllers\TenantController;
use NetOs\Vitals\Http\Middleware\Authorize;

$middleware = (array) config('vitals.middleware', ['web']);

Route::group([
    'domain' => config('vitals.domain'),
    'prefix' => config('vitals.path', 'vitals'),
    'as' => 'vitals.',
], function () use ($middleware) {
    Route::get('status.json', StatusController::class)
        ->middleware([...$middleware, Authorize::class.':token'])
        ->name('status');

    Route::middleware([...$middleware, Authorize::class])->group(function () {
        Route::get('/', OverviewController::class)->name('overview');
        Route::post('run', RunAllController::class)->name('run');
        Route::get('tenants/{tenant}', TenantController::class)->name('tenant');
        Route::post('tenants/{tenant}/run', RunTenantController::class)->name('tenant.run');
        Route::get('checks/{check}', CheckController::class)->name('check');
        Route::post('checks/{check}/run', RunCheckController::class)->name('check.run');
        Route::post('mutes', StoreMuteController::class)->name('mutes.store');
        Route::delete('mutes/{mute}', DeleteMuteController::class)->whereNumber('mute')->name('mutes.destroy');
    });
});
