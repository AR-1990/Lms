<?php

use App\Http\Controllers\ERP\AccountsController;
use Illuminate\Support\Facades\Route;

Route::prefix('accounts')->group(function () {
    Route::get('dashboard', [AccountsController::class, 'dashboard'])->middleware('permission:manage-fees,view-payroll,view-fees')->name('accounts.dashboard');
    Route::get('collections', [AccountsController::class, 'collections'])->middleware('permission:manage-fees')->name('accounts.collections');
    Route::get('challans', [AccountsController::class, 'challans'])->middleware('permission:view-fees')->name('accounts.challans');
    Route::get('payroll', [AccountsController::class, 'payroll'])->middleware('permission:view-payroll')->name('accounts.payroll');
});
