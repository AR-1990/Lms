<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Portal\AccountsPortalController;
use App\Http\Controllers\Portal\AdminPortalController;
use App\Http\Controllers\Portal\AdminSettingsController;
use App\Http\Controllers\Portal\ParentPortalController;
use App\Http\Controllers\Portal\StudentPortalController;
use App\Http\Controllers\Portal\TeacherPortalController;
use App\Http\Controllers\SchoolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Pinnacle International Academy
|--------------------------------------------------------------------------
*/

Route::get('/', [SchoolController::class, 'home'])->name('home');
Route::get('/about', [SchoolController::class, 'about'])->name('about');
Route::get('/academics', [SchoolController::class, 'academics'])->name('academics');
Route::get('/admissions', [SchoolController::class, 'admissions'])->name('admissions');
Route::get('/campus', [SchoolController::class, 'campus'])->name('campus');
Route::get('/news', [SchoolController::class, 'news'])->name('news');
Route::get('/contact', [SchoolController::class, 'contact'])->name('contact');
Route::get('/portal', [SchoolController::class, 'portal'])->name('portal');
Route::get('/login', [SchoolController::class, 'login'])->name('login');

Route::post('/login', [SchoolController::class, 'handleLogin'])->name('login.submit');
Route::post('/inquiry', [SchoolController::class, 'submitInquiry'])->name('inquiry.submit');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [SchoolController::class, 'logout'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminPortalController::class, 'users'])->middleware('permission:manage-users')->name('users');
        Route::get('/roles', [AdminPortalController::class, 'roles'])->middleware('permission:manage-roles')->name('roles');
        Route::get('/reports', [AdminPortalController::class, 'reports'])->middleware('permission:view-stats')->name('reports');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->middleware('permission:manage-settings')->name('settings');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('permission:manage-settings')->name('settings.update');
    });

    Route::prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/classes', [TeacherPortalController::class, 'classes'])->middleware('permission:manage-classes')->name('classes');
        Route::get('/attendance', [TeacherPortalController::class, 'attendance'])->middleware('permission:record-attendance')->name('attendance');
        Route::get('/grades', [TeacherPortalController::class, 'grades'])->middleware('permission:submit-grades')->name('grades');
    });

    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/courses', [StudentPortalController::class, 'courses'])->middleware('permission:view-courses')->name('courses');
        Route::get('/attendance', [StudentPortalController::class, 'attendance'])->middleware('permission:view-attendance')->name('attendance');
        Route::get('/grades', [StudentPortalController::class, 'grades'])->middleware('permission:view-grades')->name('grades');
    });

    Route::prefix('parent')->name('parent.')->group(function () {
        Route::get('/children', [ParentPortalController::class, 'children'])->middleware('permission:view-children')->name('children');
        Route::get('/fees', [ParentPortalController::class, 'fees'])->middleware('permission:view-fees')->name('fees');
        Route::get('/notices', [ParentPortalController::class, 'notices'])->middleware('permission:view-notices')->name('notices');
    });

    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/collections', [AccountsPortalController::class, 'collections'])->middleware('permission:manage-fees')->name('collections');
        Route::get('/challans', [AccountsPortalController::class, 'challans'])->middleware('permission:view-fees')->name('challans');
        Route::get('/payroll', [AccountsPortalController::class, 'payroll'])->middleware('permission:view-payroll')->name('payroll');
    });
});
