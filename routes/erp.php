<?php

use App\Http\Controllers\ERP\AccountsController;
use App\Http\Controllers\ERP\AdminController;
use App\Http\Controllers\ERP\AuthController;
use App\Http\Controllers\ERP\ParentController;
use App\Http\Controllers\ERP\PermissionController;
use App\Http\Controllers\ERP\RoleController;
use App\Http\Controllers\ERP\StudentController;
use App\Http\Controllers\ERP\TeacherController;
use App\Http\Controllers\ERP\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ERP & Academic Management API Routes
|--------------------------------------------------------------------------
|
| Unified API routes for Web ERP Dashboard and Mobile Apps (iOS/Android).
| All endpoints return standardized JSON format via ApiResponse trait.
| Prefix: /api/erp
|
*/

// ==========================================
// 1. PUBLIC AUTHENTICATION ROUTES (Web & Mobile)
// ==========================================
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
});

// ==========================================
// 2. PROTECTED ROUTES (Requires Sanctum Bearer Token)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth Profile & Token Lifecycle
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    });

    // ==========================================
    // 3. DYNAMIC ROLE & PERMISSION MANAGEMENT (Permission Driven)
    // Create unlimited custom roles, assign permissions, and sync users
    // ==========================================
    Route::middleware('permission:manage-roles')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('roles/assign-user', [RoleController::class, 'assignUserRole'])->name('roles.assign');
        Route::post('roles/revoke-user', [RoleController::class, 'revokeUserRole'])->name('roles.revoke');
        Route::post('roles/sync-user', [RoleController::class, 'syncUserRoles'])->name('roles.sync');
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::post('roles/{role}/permissions', [PermissionController::class, 'assignRolePermissions'])->name('roles.permissions');
    });

    Route::middleware('permission:manage-users')->apiResource('users', UserController::class);

    Route::prefix('admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->middleware('permission:view-stats')->name('admin.dashboard');
        Route::get('stats', [AdminController::class, 'stats'])->middleware('permission:view-stats')->name('admin.stats');
    });

    // ==========================================
    // 4. TEACHER PORTAL ROUTES (Permission Driven)
    // ==========================================
    Route::prefix('teacher')->group(function () {
        Route::get('dashboard', [TeacherController::class, 'dashboard'])->middleware('permission:manage-classes,record-attendance,submit-grades')->name('teacher.dashboard');
        Route::get('classes', [TeacherController::class, 'classes'])->middleware('permission:manage-classes')->name('teacher.classes');
        Route::post('attendance', [TeacherController::class, 'recordAttendance'])->middleware('permission:record-attendance')->name('teacher.attendance');
        Route::post('grades', [TeacherController::class, 'submitGrades'])->middleware('permission:submit-grades')->name('teacher.grades');
    });

    // ==========================================
    // 5. STUDENT PORTAL ROUTES (Permission Driven)
    // ==========================================
    Route::prefix('student')->group(function () {
        Route::get('dashboard', [StudentController::class, 'dashboard'])->middleware('permission:view-courses,view-attendance,view-grades')->name('student.dashboard');
        Route::get('courses', [StudentController::class, 'courses'])->middleware('permission:view-courses')->name('student.courses');
        Route::get('attendance', [StudentController::class, 'attendance'])->middleware('permission:view-attendance')->name('student.attendance');
        Route::get('grades', [StudentController::class, 'grades'])->middleware('permission:view-grades')->name('student.grades');
    });

    // ==========================================
    // 6. PARENT PORTAL ROUTES (Permission Driven)
    // ==========================================
    Route::prefix('parent')->group(function () {
        Route::get('dashboard', [ParentController::class, 'dashboard'])->middleware('permission:view-children,view-fees,view-attendance,view-grades,view-notices')->name('parent.dashboard');
        Route::get('children', [ParentController::class, 'children'])->middleware('permission:view-children')->name('parent.children');
        Route::get('fees', [ParentController::class, 'fees'])->middleware('permission:view-fees')->name('parent.fees');
        Route::get('notices', [ParentController::class, 'notices'])->middleware('permission:view-notices')->name('parent.notices');
    });

    // ==========================================
    // 7. ACCOUNTS PORTAL ROUTES (Permission Driven)
    // ==========================================
    Route::prefix('accounts')->group(function () {
        Route::get('dashboard', [AccountsController::class, 'dashboard'])->middleware('permission:manage-fees,view-payroll,view-fees')->name('accounts.dashboard');
        Route::get('collections', [AccountsController::class, 'collections'])->middleware('permission:manage-fees')->name('accounts.collections');
        Route::get('challans', [AccountsController::class, 'challans'])->middleware('permission:view-fees')->name('accounts.challans');
        Route::get('payroll', [AccountsController::class, 'payroll'])->middleware('permission:view-payroll')->name('accounts.payroll');
    });

});
