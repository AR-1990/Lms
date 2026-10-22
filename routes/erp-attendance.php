<?php

use App\Http\Controllers\ERP\AttendanceController;
use App\Http\Controllers\ERP\CalendarController;
use App\Http\Controllers\ERP\TimetableController;
use Illuminate\Support\Facades\Route;

/*
| Timetable, calendar, student & staff attendance, student leave (PLAN.md §4, §7, §14).
*/

Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::get('timetable/mine', [TimetableController::class, 'mine'])->name('timetable.mine');
Route::get('attendance/students/{student}', [AttendanceController::class, 'student'])->name('attendance.student');
Route::get('leave-requests', [AttendanceController::class, 'leaves'])->name('leave-requests.index');
Route::post('leave-requests', [AttendanceController::class, 'storeLeave'])->name('leave-requests.store');

Route::middleware('permission:manage-calendar')->group(function () {
    Route::get('calendar/datatable', [CalendarController::class, 'datatable'])->name('calendar.datatable');
    Route::post('calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('calendar/{calendar_event}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('calendar/{calendar_event}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
});

Route::get('timetable/sections/{section}', [TimetableController::class, 'section'])->middleware('permission:manage-timetable,record-attendance')->name('timetable.section');

Route::middleware('permission:manage-timetable')->group(function () {
    Route::post('timetable-slots', [TimetableController::class, 'store'])->name('timetable-slots.store');
    Route::delete('timetable-slots/{timetable_slot}', [TimetableController::class, 'destroy'])->name('timetable-slots.destroy');
});

Route::post('attendance/scan', [AttendanceController::class, 'scan'])->middleware(['permission:record-attendance,manage-staff-attendance', 'throttle:120,1'])->name('attendance.scan');

Route::middleware('permission:record-attendance')->group(function () {
    Route::get('attendance/sheet', [AttendanceController::class, 'sheet'])->name('attendance.sheet');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('attendance/monthly', [AttendanceController::class, 'monthly'])->name('attendance.monthly');
    Route::get('leave-requests/datatable', [AttendanceController::class, 'leavesDatatable'])->name('leave-requests.datatable');
    Route::post('leave-requests/{student_leave_request}/decision', [AttendanceController::class, 'decideLeave'])->name('leave-requests.decision');
});

Route::middleware('permission:manage-staff-attendance')->group(function () {
    Route::get('attendance/staff', [AttendanceController::class, 'staffSheet'])->name('attendance.staff');
    Route::post('attendance/staff', [AttendanceController::class, 'storeStaff'])->name('attendance.staff.store');
});
