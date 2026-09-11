<?php

use App\Http\Controllers\ERP\ParentController;
use Illuminate\Support\Facades\Route;

Route::prefix('parent')->group(function () {
    Route::get('dashboard', [ParentController::class, 'dashboard'])->middleware('permission:view-children,view-fees,view-attendance,view-grades,view-notices')->name('parent.dashboard');
    Route::get('children', [ParentController::class, 'children'])->middleware('permission:view-children')->name('parent.children');
    Route::get('fees', [ParentController::class, 'fees'])->middleware('permission:view-fees')->name('parent.fees');
    Route::get('notices', [ParentController::class, 'notices'])->middleware('permission:view-notices')->name('parent.notices');
});
