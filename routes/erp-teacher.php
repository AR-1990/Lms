<?php

use App\Http\Controllers\ERP\TeacherController;
use Illuminate\Support\Facades\Route;

Route::prefix('teacher')->group(function () {
    Route::get('dashboard', [TeacherController::class, 'dashboard'])->middleware('permission:manage-classes,record-attendance,submit-grades')->name('teacher.dashboard');
    Route::get('classes', [TeacherController::class, 'classes'])->middleware('permission:manage-classes')->name('teacher.classes');
    Route::post('attendance', [TeacherController::class, 'recordAttendance'])->middleware('permission:record-attendance')->name('teacher.attendance');
    Route::post('grades', [TeacherController::class, 'submitGrades'])->middleware('permission:submit-grades')->name('teacher.grades');
});
