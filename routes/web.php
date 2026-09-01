<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GLS szállítási modul útvonalai
|--------------------------------------------------------------------------
|
| Csomagváz: a GLS admin/callback útvonalak a szállítási funkció
| fejlesztésekor kerülnek ide, a testvércsomagok mintájára:
|
| Route::domain(getAdminDomain())->middleware(['web', 'admin_share', 'auth:admin'])
|     ->prefix('webshop/gls')->name('admin.webshop.gls.')->group(function () {
|         ...
|     });
|
*/
