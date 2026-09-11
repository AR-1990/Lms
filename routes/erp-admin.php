<?php

use App\Http\Controllers\ERP\AdminController;
use App\Http\Controllers\ERP\SystemSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::get('dashboard', [AdminController::class, 'dashboard'])->middleware('permission:view-stats')->name('admin.dashboard');
    Route::get('stats', [AdminController::class, 'stats'])->middleware('permission:view-stats')->name('admin.stats');
    Route::get('settings', [SystemSettingsController::class, 'show'])->middleware('permission:manage-settings')->name('admin.settings');
    Route::put('settings', [SystemSettingsController::class, 'update'])->middleware('permission:manage-settings')->name('admin.settings.update');
    Route::get('reference/countries', [SystemSettingsController::class, 'countries'])->middleware('permission:manage-settings')->name('admin.reference.countries');
    Route::get('reference/regions', [SystemSettingsController::class, 'regions'])->middleware('permission:manage-settings')->name('admin.reference.regions');
    Route::get('reference/currencies', [SystemSettingsController::class, 'currencies'])->middleware('permission:manage-settings')->name('admin.reference.currencies');
});
