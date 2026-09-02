<?php

use Illuminate\Support\Facades\Route;
use Weboldalnet\CommerceGls\Http\Controllers\Admin\GlsSettingController;

// FIGYELEM: a platformon 'admin_share' a middleware alias, nem 'admin'.
Route::domain(getAdminDomain())
    ->middleware(['web', 'admin_share', 'auth:admin'])
    ->prefix('webshop/gls')
    ->name('admin.webshop.gls.')
    ->group(function () {
        Route::get('/settings', [GlsSettingController::class, 'index'])->name('settings');
        Route::post('/settings', [GlsSettingController::class, 'update'])->name('settings.update');
        Route::post('/test-connection', [GlsSettingController::class, 'testConnection'])->name('test-connection');
    });
