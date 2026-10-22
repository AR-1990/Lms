<?php

use App\Http\Controllers\ERP\PerformanceController;
use Illuminate\Support\Facades\Route;

/*
| Student performance tracker (PLAN.md §20). Students and parents read their own profile.
*/

Route::get('performance/students/{student}', [PerformanceController::class, 'student'])->name('performance.student');

Route::middleware('permission:view-performance')->group(function () {
    Route::get('performance/overview', [PerformanceController::class, 'overview'])->name('performance.overview');
    Route::post('performance/refresh', [PerformanceController::class, 'refresh'])->name('performance.refresh');
    Route::get('performance/skills', [PerformanceController::class, 'skillSheet'])->name('performance.skills');
    Route::post('performance/skills', [PerformanceController::class, 'saveSkills'])->name('performance.skills.store');
    Route::post('performance/goals', [PerformanceController::class, 'storeGoal'])->name('performance.goals.store');
    Route::put('performance/goals/{student_goal}', [PerformanceController::class, 'updateGoal'])->name('performance.goals.update');
    Route::delete('performance/goals/{student_goal}', [PerformanceController::class, 'destroyGoal'])->name('performance.goals.destroy');
    Route::get('performance/alerts', [PerformanceController::class, 'alerts'])->name('performance.alerts');
    Route::put('performance/alerts/{performance_alert}', [PerformanceController::class, 'updateAlert'])->name('performance.alerts.update');
});
