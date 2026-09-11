<?php

use App\Http\Controllers\ERP\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('student')->group(function () {
    Route::get('dashboard', [StudentController::class, 'dashboard'])->middleware('permission:view-courses,view-attendance,view-grades')->name('student.dashboard');
    Route::get('courses', [StudentController::class, 'courses'])->middleware('permission:view-courses')->name('student.courses');
    Route::get('attendance', [StudentController::class, 'attendance'])->middleware('permission:view-attendance')->name('student.attendance');
    Route::get('grades', [StudentController::class, 'grades'])->middleware('permission:view-grades')->name('student.grades');
});
