<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ERP\AttachmentController;
use App\Http\Controllers\Portal\AcademicsPortalController;
use App\Http\Controllers\Portal\AccountingPortalController;
use App\Http\Controllers\Portal\AccountsPortalController;
use App\Http\Controllers\Portal\AdminPortalController;
use App\Http\Controllers\Portal\AdminSettingsController;
use App\Http\Controllers\Portal\AdminUserController;
use App\Http\Controllers\Portal\AttendancePortalController;
use App\Http\Controllers\Portal\CardsPortalController;
use App\Http\Controllers\Portal\ExamsPortalController;
use App\Http\Controllers\Portal\FeesPortalController;
use App\Http\Controllers\Portal\HrPortalController;
use App\Http\Controllers\Portal\ParentPortalController;
use App\Http\Controllers\Portal\StudentPortalController;
use App\Http\Controllers\Portal\StudentsPortalController;
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
Route::get('/verify/{token}', [SchoolController::class, 'verifyCard'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:30,1')->name('cards.verify');
Route::post('/inquiry', [SchoolController::class, 'submitInquiry'])->middleware('throttle:10,1')->name('inquiry.submit');

// Short-lived signed links to private files (web and mobile app); the signature is the permission.
Route::get('/files/{attachment}', [AttachmentController::class, 'download'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('attachments.download');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [SchoolController::class, 'logout'])->name('logout');
    Route::get('/my-id-card', [CardsPortalController::class, 'mine'])->name('my-id-card');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:manage-users')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index'])->name('users');
            Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
            Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        });
        Route::get('/lookups', [AdminPortalController::class, 'lookups'])->middleware('permission:manage-lookups')->name('lookups');
        Route::prefix('website')->name('website.')->middleware('permission:manage-website')->group(function () {
            foreach (['posts', 'stats', 'testimonials', 'faqs'] as $websiteType) {
                Route::get('/'.$websiteType, [AdminPortalController::class, 'website'])->defaults('type', $websiteType)->name($websiteType);
            }
        });
        Route::prefix('id-cards')->name('cards.')->middleware('permission:manage-cards')->group(function () {
            Route::get('/', [CardsPortalController::class, 'cards'])->name('index');
            Route::get('/templates', [CardsPortalController::class, 'templates'])->name('templates');
            Route::get('/templates/{card_template}/design', [CardsPortalController::class, 'design'])->name('design');
        });
        Route::get('/approvals', [AdminPortalController::class, 'approvals'])->middleware('permission:approve-requests')->name('approvals');
        Route::get('/activity-log', [AdminPortalController::class, 'activityLog'])->middleware('permission:view-activity-log')->name('activity-log');
        Route::get('/campuses', [AdminPortalController::class, 'campuses'])->middleware('permission:manage-campuses')->name('campuses');
        Route::get('/numbering', [AdminPortalController::class, 'numbering'])->middleware('permission:manage-numbering')->name('numbering');
        Route::middleware('permission:manage-students')->group(function () {
            Route::get('/students', [StudentsPortalController::class, 'students'])->name('students');
            Route::get('/students/{student}', [StudentsPortalController::class, 'show'])->name('students.show');
        });
        Route::prefix('admissions')->name('admissions.')->middleware('permission:manage-admissions')->group(function () {
            Route::get('/applications', [StudentsPortalController::class, 'applications'])->name('applications');
            Route::get('/inquiries', [StudentsPortalController::class, 'inquiries'])->name('inquiries');
        });
        Route::prefix('hr')->name('hr.')->group(function () {
            Route::middleware('permission:manage-hr')->group(function () {
                Route::get('/employees', [HrPortalController::class, 'employees'])->name('employees');
                Route::get('/assignments', [HrPortalController::class, 'assignments'])->name('assignments');
                Route::get('/departments', [HrPortalController::class, 'departments'])->name('departments');
                Route::get('/designations', [HrPortalController::class, 'designations'])->name('designations');
            });
            Route::middleware('permission:manage-recruitment')->group(function () {
                Route::get('/candidates', [HrPortalController::class, 'candidates'])->name('candidates');
                Route::get('/openings', [HrPortalController::class, 'openings'])->name('openings');
            });
            Route::get('/purchases', [HrPortalController::class, 'purchases'])->middleware('permission:manage-purchases')->name('purchases');
        });
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::middleware('permission:record-attendance')->group(function () {
                Route::get('/', [AttendancePortalController::class, 'register'])->name('register');
                Route::get('/monthly', [AttendancePortalController::class, 'monthly'])->name('monthly');
                Route::get('/leave', [AttendancePortalController::class, 'leaves'])->name('leaves');
            });
            Route::get('/staff', [AttendancePortalController::class, 'staff'])->middleware('permission:manage-staff-attendance')->name('staff');
            Route::get('/scan', [AttendancePortalController::class, 'scan'])->middleware('permission:record-attendance,manage-staff-attendance')->name('scan');
        });
        Route::prefix('exams')->name('exams.')->group(function () {
            Route::get('/marks', [ExamsPortalController::class, 'marks'])->middleware('permission:submit-grades,manage-exams')->name('marks');
            Route::middleware('permission:manage-exams')->group(function () {
                Route::get('/', [ExamsPortalController::class, 'exams'])->name('index');
                foreach (['terms', 'scales', 'halls', 'skills', 'rules'] as $setupType) {
                    Route::get('/setup/'.$setupType, [ExamsPortalController::class, 'setup'])->defaults('type', $setupType)->name('setup.'.$setupType);
                }
            });
        });
        Route::prefix('performance')->name('performance.')->middleware('permission:view-performance')->group(function () {
            foreach (['overview' => '/', 'skills' => '/skills', 'alerts' => '/alerts'] as $screen => $path) {
                Route::get($path, [ExamsPortalController::class, 'performance'])->defaults('screen', $screen)->name($screen);
            }
        });
        Route::middleware('permission:manage-diary')->group(function () {
            Route::get('/diary', [AttendancePortalController::class, 'diary'])->name('diary');
            Route::get('/homework', [AttendancePortalController::class, 'homework'])->name('homework');
        });
        Route::get('/academics/timetable', [AttendancePortalController::class, 'timetable'])->middleware('permission:manage-timetable')->name('academics.timetable');
        Route::get('/academics/calendar', [AttendancePortalController::class, 'calendar'])->middleware('permission:manage-calendar')->name('academics.calendar');
        Route::prefix('academics')->name('academics.')->middleware('permission:manage-academics')->group(function () {
            Route::get('/sessions', [AcademicsPortalController::class, 'sessions'])->name('sessions');
            Route::get('/classes', [AcademicsPortalController::class, 'classes'])->name('classes');
            Route::get('/sections', [AcademicsPortalController::class, 'sections'])->name('sections');
            Route::get('/subjects', [AcademicsPortalController::class, 'subjects'])->name('subjects');
            Route::get('/curriculum', [AcademicsPortalController::class, 'curriculum'])->name('curriculum');
            Route::get('/periods', [AcademicsPortalController::class, 'periods'])->name('periods');
        });
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

        Route::prefix('fees')->name('fees.')->group(function () {
            Route::middleware('permission:manage-fees')->group(function () {
                Route::get('/challans', [FeesPortalController::class, 'challans'])->name('challans');
                Route::get('/receipts', [FeesPortalController::class, 'receipts'])->name('receipts');
            });
            Route::middleware('permission:manage-fee-setup')->group(function () {
                Route::get('/structures', [FeesPortalController::class, 'structures'])->name('structures');
                Route::get('/heads', [FeesPortalController::class, 'heads'])->name('heads');
                Route::get('/concessions', [FeesPortalController::class, 'concessions'])->name('concessions');
                Route::get('/concession-types', [FeesPortalController::class, 'concessionTypes'])->name('concession-types');
            });
        });

        Route::get('/chart', [AccountingPortalController::class, 'chart'])->middleware('permission:view-ledger,manage-accounts')->name('chart');
        Route::get('/chart/{account}/ledger', [AccountingPortalController::class, 'ledger'])->middleware('permission:view-ledger')->name('ledger');
        Route::get('/vouchers', [AccountingPortalController::class, 'vouchers'])->middleware('permission:view-ledger,manage-vouchers,post-vouchers')->name('vouchers');
        Route::get('/trial-balance', [AccountingPortalController::class, 'trialBalance'])->middleware('permission:view-ledger')->name('trial-balance');
        Route::middleware('permission:manage-accounts')->group(function () {
            Route::get('/fiscal-years', [AccountingPortalController::class, 'fiscalYears'])->name('fiscal-years');
            Route::get('/voucher-types', [AccountingPortalController::class, 'voucherTypes'])->name('voucher-types');
            Route::get('/payment-methods', [AccountingPortalController::class, 'paymentMethods'])->name('payment-methods');
            Route::get('/bank-accounts', [AccountingPortalController::class, 'bankAccounts'])->name('bank-accounts');
            Route::get('/vendors', [AccountingPortalController::class, 'vendors'])->name('vendors');
        });
    });
});
