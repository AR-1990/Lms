<?php

use App\Http\Controllers\ERP\AdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::get('dashboard', [AdminController::class, 'dashboard'])->middleware('permission:view-stats')->name('admin.dashboard');
    Route::get('stats', [AdminController::class, 'stats'])->middleware('permission:view-stats')->name('admin.stats');
});
