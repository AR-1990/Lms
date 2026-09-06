<?php

use App\Http\Controllers\ERP\AdminController;
use App\Http\Controllers\ERP\AuthController;
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
    // 3. DYNAMIC ROLE & PERMISSION MANAGEMENT (Admin Only)
    // Create unlimited custom roles, assign permissions & sync users
    // ==========================================
    Route::middleware('role:admin')->group(function () {
        // Role CRUD
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        // User Role Assignments
        Route::post('roles/assign-user', [RoleController::class, 'assignUserRole'])->name('roles.assign');
        Route::post('roles/revoke-user', [RoleController::class, 'revokeUserRole'])->name('roles.revoke');
        Route::post('roles/sync-user', [RoleController::class, 'syncUserRoles'])->name('roles.sync');

        // Permission Management
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::post('roles/{role}/permissions', [PermissionController::class, 'assignRolePermissions'])->name('roles.permissions');

        // User Management (Admin CRUD)
        Route::apiResource('users', UserController::class);

        // Admin Portal Dashboard & Analytics
        Route::prefix('admin')->group(function () {
            Route::get('dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
            Route::get('stats', [AdminController::class, 'stats'])->name('admin.stats');
        });
    });

    // ==========================================
    // 4. TEACHER PORTAL ROUTES (Teacher & Admin)
    // ==========================================
    Route::middleware('role:teacher,admin')->prefix('teacher')->group(function () {
        Route::get('dashboard', [TeacherController::class, 'dashboard'])->name('teacher.dashboard');
        Route::get('classes', [TeacherController::class, 'classes'])->name('teacher.classes');
        Route::post('attendance', [TeacherController::class, 'recordAttendance'])->name('teacher.attendance');
        Route::post('grades', [TeacherController::class, 'submitGrades'])->name('teacher.grades');
    });

    // ==========================================
    // 5. STUDENT PORTAL ROUTES (Student & Admin)
    // ==========================================
    Route::middleware('role:student,admin')->prefix('student')->group(function () {
        Route::get('dashboard', [StudentController::class, 'dashboard'])->name('student.dashboard');
        Route::get('courses', [StudentController::class, 'courses'])->name('student.courses');
        Route::get('attendance', [StudentController::class, 'attendance'])->name('student.attendance');
        Route::get('grades', [StudentController::class, 'grades'])->name('student.grades');
    });

});
