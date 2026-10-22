<?php

use App\Http\Controllers\ERP\DepartmentController;
use App\Http\Controllers\ERP\DesignationController;
use App\Http\Controllers\ERP\EmployeeController;
use App\Http\Controllers\ERP\JobApplicationController;
use App\Http\Controllers\ERP\JobOpeningController;
use App\Http\Controllers\ERP\PurchaseRequestController;
use App\Http\Controllers\ERP\TeacherAssignmentController;
use Illuminate\Support\Facades\Route;

/*
| Employees and recruitment (PLAN.md §12–13).
*/

Route::middleware('permission:manage-hr')->group(function () {
    Route::get('departments/datatable', [DepartmentController::class, 'datatable'])->name('departments.datatable');
    Route::apiResource('departments', DepartmentController::class);

    Route::get('designations/datatable', [DesignationController::class, 'datatable'])->name('designations.datatable');
    Route::apiResource('designations', DesignationController::class);

    Route::get('employees/datatable', [EmployeeController::class, 'datatable'])->name('employees.datatable');
    Route::post('employees/{employee}/status', [EmployeeController::class, 'changeStatus'])->name('employees.status');
    Route::post('employees/{employee}/portal-account', [EmployeeController::class, 'createPortalAccount'])->name('employees.portal-account');
    Route::apiResource('employees', EmployeeController::class);

    Route::get('teacher-assignments/datatable', [TeacherAssignmentController::class, 'datatable'])->name('teacher-assignments.datatable');
    Route::apiResource('teacher-assignments', TeacherAssignmentController::class);
});

Route::middleware('permission:manage-recruitment')->group(function () {
    Route::get('job-openings/datatable', [JobOpeningController::class, 'datatable'])->name('job-openings.datatable');
    Route::apiResource('job-openings', JobOpeningController::class);

    Route::get('job-applications/datatable', [JobApplicationController::class, 'datatable'])->name('job-applications.datatable');
    Route::post('job-applications/{job_application}/status', [JobApplicationController::class, 'changeStatus'])->name('job-applications.status');
    Route::post('job-applications/{job_application}/interviews', [JobApplicationController::class, 'storeInterview'])->name('job-applications.interviews.store');
    Route::put('job-interviews/{job_interview}', [JobApplicationController::class, 'updateInterview'])->name('job-interviews.update');
    Route::post('job-applications/{job_application}/hire', [JobApplicationController::class, 'hire'])->name('job-applications.hire');
    Route::apiResource('job-applications', JobApplicationController::class);
});

Route::middleware('permission:manage-purchases')->group(function () {
    Route::get('purchase-requests/datatable', [PurchaseRequestController::class, 'datatable'])->name('purchase-requests.datatable');
    Route::apiResource('purchase-requests', PurchaseRequestController::class);
});
