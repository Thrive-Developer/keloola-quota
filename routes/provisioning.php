<?php

use Illuminate\Support\Facades\Route;
use Keloola\Quota\Http\Controllers\QuotaProvisionController;
use Keloola\Quota\Http\Controllers\QuotaCheckController;

Route::group([
    'prefix'     => config('keloola-quota.provisioning.route_prefix', 'api/quota'),
    'middleware' => config('keloola-quota.provisioning.middleware', ['keloola.quota.context']),
], function () {
    // SSO pushes plan quota definitions here (install + updates).
    Route::post('/provision', [QuotaProvisionController::class, 'store'])
        ->name('quota.provision');

    // SSO removes a plan's quota (uninstall / plan deleted).
    Route::delete('/provision/{appPlanId}', [QuotaProvisionController::class, 'destroy'])
        ->name('quota.deprovision');

    // Endpoint for checking quota by metric
    Route::get('/check/{metricCode}', [QuotaCheckController::class, 'show'])
        ->name('quota.check');
});
