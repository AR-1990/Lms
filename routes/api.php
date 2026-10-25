<?php

use App\Http\Controllers\ERP\AcademicSessionController;
use App\Http\Controllers\ERP\AccountController;
use App\Http\Controllers\ERP\AccountsController;
use App\Http\Controllers\ERP\ActivityLogController;
use App\Http\Controllers\ERP\AdminController;
use App\Http\Controllers\ERP\AdmissionApplicationController;
use App\Http\Controllers\ERP\AdmissionInquiryController;
use App\Http\Controllers\ERP\AnnouncementController;
use App\Http\Controllers\ERP\AppraisalController;
use App\Http\Controllers\ERP\ApprovalController;
use App\Http\Controllers\ERP\AssetController;
use App\Http\Controllers\ERP\AttachmentController;
use App\Http\Controllers\ERP\AttendanceController;
use App\Http\Controllers\ERP\AttendanceKioskController;
use App\Http\Controllers\ERP\AuthController;
use App\Http\Controllers\ERP\BackupController;
use App\Http\Controllers\ERP\BankAccountController;
use App\Http\Controllers\ERP\CalendarController;
use App\Http\Controllers\ERP\CampusController;
use App\Http\Controllers\ERP\ChatController;
use App\Http\Controllers\ERP\ChatMonitorController;
use App\Http\Controllers\ERP\CounsellingController;
use App\Http\Controllers\ERP\CurriculumController;
use App\Http\Controllers\ERP\DepartmentController;
use App\Http\Controllers\ERP\DesignationController;
use App\Http\Controllers\ERP\DeviceController;
use App\Http\Controllers\ERP\DiaryController;
use App\Http\Controllers\ERP\EmployeeController;
use App\Http\Controllers\ERP\EmployeeExitController;
use App\Http\Controllers\ERP\ExamController;
use App\Http\Controllers\ERP\ExpenseController;
use App\Http\Controllers\ERP\FamilyController;
use App\Http\Controllers\ERP\FeeAutomationController;
use App\Http\Controllers\ERP\FeeInvoiceController;
use App\Http\Controllers\ERP\FeePaymentController;
use App\Http\Controllers\ERP\FeeSetupController;
use App\Http\Controllers\ERP\FiscalYearController;
use App\Http\Controllers\ERP\HomeworkController;
use App\Http\Controllers\ERP\IdCardController;
use App\Http\Controllers\ERP\ImportController;
use App\Http\Controllers\ERP\InventoryController;
use App\Http\Controllers\ERP\JobApplicationController;
use App\Http\Controllers\ERP\JobOpeningController;
use App\Http\Controllers\ERP\JournalEntryController;
use App\Http\Controllers\ERP\JumpToController;
use App\Http\Controllers\ERP\LeaveController;
use App\Http\Controllers\ERP\LoanController;
use App\Http\Controllers\ERP\LookupController;
use App\Http\Controllers\ERP\MarksController;
use App\Http\Controllers\ERP\MyHrController;
use App\Http\Controllers\ERP\NotificationController;
use App\Http\Controllers\ERP\NumberSequenceController;
use App\Http\Controllers\ERP\OnlineTestController;
use App\Http\Controllers\ERP\ParentController;
use App\Http\Controllers\ERP\PaymentMethodController;
use App\Http\Controllers\ERP\PayrollController;
use App\Http\Controllers\ERP\PerformanceController;
use App\Http\Controllers\ERP\PeriodController;
use App\Http\Controllers\ERP\PermissionController;
use App\Http\Controllers\ERP\PromotionController;
use App\Http\Controllers\ERP\PurchaseRequestController;
use App\Http\Controllers\ERP\ReportController;
use App\Http\Controllers\ERP\RoleController;
use App\Http\Controllers\ERP\SchoolClassController;
use App\Http\Controllers\ERP\SectionController;
use App\Http\Controllers\ERP\StickyNoteController;
use App\Http\Controllers\ERP\StudentCareController;
use App\Http\Controllers\ERP\StudentController;
use App\Http\Controllers\ERP\StudentLeavingController;
use App\Http\Controllers\ERP\StudentRecordController;
use App\Http\Controllers\ERP\SubjectController;
use App\Http\Controllers\ERP\SystemSettingsController;
use App\Http\Controllers\ERP\TaskController;
use App\Http\Controllers\ERP\TeacherAssignmentController;
use App\Http\Controllers\ERP\TeacherController;
use App\Http\Controllers\ERP\TimetableController;
use App\Http\Controllers\ERP\TransportController;
use App\Http\Controllers\ERP\UserController;
use App\Http\Controllers\ERP\VendorController;
use App\Http\Controllers\ERP\VoucherTypeController;
use App\Http\Controllers\ERP\WebsiteContentController;
use App\Http\Controllers\ERP\WebsitePageController;
use App\Http\Controllers\ERP\WorkShiftController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SchoolController;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

/*
| Every API route lives in this file: the web admin panel, the portals and the Android/iOS apps all call it.
| Laravel adds the /api prefix and the api middleware. Blade pages live in routes/web.php; PDFs, print
| sheets, reports, exports and file links live in routes/export.php.
*/

// Public website forms (the Blade page sends its CSRF token with the request).
// Public website content for the mobile apps (the same data as the website pages; no sign-in).
Route::prefix('site')->name('site.')->middleware([ForceJsonResponse::class, 'throttle:60,1'])->group(function () {
    Route::get('/', [PublicSiteController::class, 'index'])->name('index');
    Route::get('news', [PublicSiteController::class, 'news'])->name('news');
    Route::get('contact', [PublicSiteController::class, 'contact'])->name('contact');
    Route::get('pages/{slug}', [PublicSiteController::class, 'page'])->where('slug', '[a-z0-9-]+')->name('pages.show');
});

// Attendance kiosk at the gate (no sign-in; the secret link is the permission).
Route::prefix('kiosk/{token}')->name('kiosk.')->middleware(ForceJsonResponse::class)->where(['token' => '[A-Za-z0-9]{40}'])->group(function () {
    Route::get('/', [AttendanceKioskController::class, 'show'])->middleware('throttle:30,1')->name('show-api');
    Route::post('scan', [AttendanceKioskController::class, 'scan'])->middleware('throttle:240,1')->name('scan');
});

Route::post('inquiry', [SchoolController::class, 'submitInquiry'])->middleware([ForceJsonResponse::class, 'throttle:10,1'])->name('inquiry.submit');

// Sign in / out and password reset for the mobile apps and API clients. Accounts are only created by the school
// (admissions, HR, Users) — there is no public sign-up.
Route::middleware(ForceJsonResponse::class)->name('api.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset')->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.update');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

// ERP API (/api/erp/...): signed in with the web session or a Sanctum token, then checked per permission.
Route::middleware([ForceJsonResponse::class, 'auth:sanctum'])->prefix('erp')->name('erp.')->group(function () {
    // "Jump to" search: the pages this user may open.
    Route::get('jump-to', JumpToController::class)->name('jump-to');

    // ---------- Access ----------
    Route::middleware('permission:manage-roles')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/datatable', [RoleController::class, 'datatable'])->name('roles.datatable');
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

    Route::middleware('permission:manage-users')->group(function () {
        Route::get('users/datatable', [UserController::class, 'datatable'])->name('users.datatable');
        Route::apiResource('users', UserController::class);
    });

    // ---------- Core ----------
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::put('devices/push-token', [DeviceController::class, 'updatePushToken'])->name('devices.push-token');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    Route::get('approvals/inbox', [ApprovalController::class, 'inbox'])->name('approvals.inbox');
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('approvals/datatable', [ApprovalController::class, 'datatable'])->name('approvals.datatable');

    Route::middleware('permission:approve-requests')->group(function () {
        Route::post('approvals/{approval_request}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{approval_request}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    });

    Route::get('attachments', [AttachmentController::class, 'index'])->name('attachments.index');
    Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::patch('attachments/{attachment}/verify', [AttachmentController::class, 'verify'])->name('attachments.verify');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('lookup-options/{type}', [LookupController::class, 'options'])
        ->where('type', '[a-z][a-z0-9_]*')
        ->name('lookups.options');

    Route::middleware('permission:manage-lookups')->group(function () {
        Route::get('lookups/datatable', [LookupController::class, 'datatable'])->name('lookups.datatable');
        Route::get('lookups/types', [LookupController::class, 'types'])->name('lookups.types');
        Route::apiResource('lookups', LookupController::class);
    });

    Route::middleware('permission:manage-campuses')->group(function () {
        Route::get('campuses/datatable', [CampusController::class, 'datatable'])->name('campuses.datatable');
        Route::apiResource('campuses', CampusController::class);
    });

    Route::middleware('permission:manage-numbering')->group(function () {
        Route::get('number-sequences/datatable', [NumberSequenceController::class, 'datatable'])->name('number-sequences.datatable');
        Route::get('number-sequences', [NumberSequenceController::class, 'index'])->name('number-sequences.index');
        Route::get('number-sequences/{numberSequence}', [NumberSequenceController::class, 'show'])->name('number-sequences.show');
        Route::put('number-sequences/{numberSequence}', [NumberSequenceController::class, 'update'])->name('number-sequences.update');
    });

    Route::middleware('permission:view-activity-log')->group(function () {
        Route::get('activity-logs/datatable', [ActivityLogController::class, 'datatable'])->name('activity-logs.datatable');
        Route::get('activity-logs/filters', [ActivityLogController::class, 'filterOptions'])->name('activity-logs.filters');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    });

    Route::middleware('permission:manage-website')->prefix('website')->name('website.')->group(function () {
        // Pages, blocks and cards come first: the {type} routes below would otherwise catch them.
        Route::get('pages', [WebsitePageController::class, 'index'])->name('pages.index');
        Route::get('pages/{page}', [WebsitePageController::class, 'show'])->whereNumber('page')->name('pages.show');
        Route::put('pages/{page}', [WebsitePageController::class, 'update'])->whereNumber('page')->name('pages.update');
        Route::put('sections/{section}', [WebsitePageController::class, 'updateSection'])->whereNumber('section')->name('sections.update');
        Route::post('sections/{section}/items', [WebsitePageController::class, 'storeItem'])->whereNumber('section')->name('sections.items.store');
        Route::put('items/{item}', [WebsitePageController::class, 'updateItem'])->whereNumber('item')->name('items.update');
        Route::delete('items/{item}', [WebsitePageController::class, 'destroyItem'])->whereNumber('item')->name('items.destroy');
        Route::post('images/{kind}/{id}', [WebsitePageController::class, 'image'])->whereIn('kind', ['page', 'section', 'item'])->whereNumber('id')->name('images.store');
        Route::post('posts/{id}/image', [WebsiteContentController::class, 'image'])->whereNumber('id')->name('posts.image');
        Route::get('{type}/datatable', [WebsiteContentController::class, 'datatable'])->name('datatable');
        Route::post('{type}', [WebsiteContentController::class, 'store'])->name('store');
        Route::get('{type}/{id}', [WebsiteContentController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('{type}/{id}', [WebsiteContentController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('{type}/{id}', [WebsiteContentController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // ---------- Academics ----------
    Route::get('academics/options', [AcademicSessionController::class, 'options'])->name('academics.options');
    Route::get('periods', [PeriodController::class, 'index'])->name('periods.index');

    Route::middleware('permission:manage-academics')->group(function () {
        Route::get('academic-sessions/datatable', [AcademicSessionController::class, 'datatable'])->name('academic-sessions.datatable');
        Route::apiResource('academic-sessions', AcademicSessionController::class);

        Route::get('classes/datatable', [SchoolClassController::class, 'datatable'])->name('classes.datatable');
        Route::apiResource('classes', SchoolClassController::class);

        Route::get('sections/datatable', [SectionController::class, 'datatable'])->name('sections.datatable');
        Route::apiResource('sections', SectionController::class);

        Route::get('subjects/datatable', [SubjectController::class, 'datatable'])->name('subjects.datatable');
        Route::apiResource('subjects', SubjectController::class);

        Route::get('curriculum/datatable', [CurriculumController::class, 'datatable'])->name('curriculum.datatable');
        Route::post('curriculum/copy', [CurriculumController::class, 'copy'])->name('curriculum.copy');
        Route::apiResource('curriculum', CurriculumController::class);

        Route::get('periods/datatable', [PeriodController::class, 'datatable'])->name('periods.datatable');
        Route::apiResource('periods', PeriodController::class)->except('index');
    });

    // ---------- Ledger ----------
    // Accounts & general ledger (PLAN.md §10–11). Amounts are always in paisa.

    Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');

    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::middleware('permission:view-ledger,manage-vouchers,manage-accounts')->group(function () {
            Route::get('accounts/options', [AccountController::class, 'options'])->name('accounts.options');
            Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
            Route::get('accounts/datatable', [AccountController::class, 'datatable'])->name('accounts.datatable');
            Route::get('accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
            Route::get('fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
            Route::get('voucher-types', [VoucherTypeController::class, 'index'])->name('voucher-types.index');
        });

        Route::middleware('permission:view-ledger,manage-vouchers,post-vouchers')->group(function () {
            Route::get('vouchers', [JournalEntryController::class, 'index'])->name('vouchers.index');
            Route::get('vouchers/datatable', [JournalEntryController::class, 'datatable'])->name('vouchers.datatable');
            Route::get('vouchers/{voucher}', [JournalEntryController::class, 'show'])->name('vouchers.show');
        });

        Route::middleware('permission:view-ledger')->group(function () {
            Route::get('accounts/{account}/ledger', [AccountController::class, 'ledger'])->name('accounts.ledger');
            Route::get('trial-balance', [AccountController::class, 'trialBalance'])->name('trial-balance');
        });

        Route::middleware('permission:manage-vouchers')->group(function () {
            Route::post('vouchers', [JournalEntryController::class, 'store'])->name('vouchers.store');
            Route::put('vouchers/{voucher}', [JournalEntryController::class, 'update'])->name('vouchers.update');
            Route::delete('vouchers/{voucher}', [JournalEntryController::class, 'destroy'])->name('vouchers.destroy');
        });

        Route::middleware('permission:post-vouchers')->group(function () {
            Route::post('vouchers/{voucher}/post', [JournalEntryController::class, 'post'])->name('vouchers.post');
            Route::post('vouchers/{voucher}/reverse', [JournalEntryController::class, 'reverse'])->name('vouchers.reverse');
        });

        Route::middleware('permission:manage-accounts')->group(function () {
            Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
            Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

            Route::get('fiscal-years/datatable', [FiscalYearController::class, 'datatable'])->name('fiscal-years.datatable');
            Route::post('fiscal-years/{fiscal_year}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
            Route::post('fiscal-years/{fiscal_year}/reopen', [FiscalYearController::class, 'reopen'])->name('fiscal-years.reopen');
            Route::apiResource('fiscal-years', FiscalYearController::class)->except('index');

            Route::get('voucher-types/datatable', [VoucherTypeController::class, 'datatable'])->name('voucher-types.datatable');
            Route::apiResource('voucher-types', VoucherTypeController::class)->except('index');

            Route::get('payment-methods/datatable', [PaymentMethodController::class, 'datatable'])->name('payment-methods.datatable');
            Route::apiResource('payment-methods', PaymentMethodController::class)->except('index');

            Route::get('bank-accounts/datatable', [BankAccountController::class, 'datatable'])->name('bank-accounts.datatable');
            Route::apiResource('bank-accounts', BankAccountController::class);

            Route::get('vendors/datatable', [VendorController::class, 'datatable'])->name('vendors.datatable');
            Route::apiResource('vendors', VendorController::class);
        });
    });

    // ---------- Hr ----------
    // Employees and recruitment (PLAN.md §12–13); leave, payroll and loans (§14); employee self-service (my-hr).

    Route::middleware('permission:manage-hr')->group(function () {
        // Staff exits: clearance by area, final settlement (PV), experience letter (routes/export.php).
        Route::get('employee-exits/datatable', [EmployeeExitController::class, 'datatable'])->name('employee-exits.datatable');
        Route::post('employee-exits', [EmployeeExitController::class, 'store'])->name('employee-exits.store');
        Route::get('employee-exits/{employee_exit}', [EmployeeExitController::class, 'show'])->name('employee-exits.show');
        Route::post('employee-exits/{employee_exit}/preview', [EmployeeExitController::class, 'preview'])->name('employee-exits.preview');
        Route::post('employee-exits/{employee_exit}/settle', [EmployeeExitController::class, 'settle'])->name('employee-exits.settle');
        Route::post('employee-exits/{employee_exit}/cancel', [EmployeeExitController::class, 'cancel'])->name('employee-exits.cancel');
        Route::put('exit-clearances/{exit_clearance}', [EmployeeExitController::class, 'clear'])->name('exit-clearances.update');

        Route::get('work-shifts/datatable', [WorkShiftController::class, 'datatable'])->name('work-shifts.datatable');
        Route::apiResource('work-shifts', WorkShiftController::class)->except('index');

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

    Route::middleware('permission:manage-leave')->group(function () {
        Route::get('leave-types/datatable', [LeaveController::class, 'typesDatatable'])->name('leave-types.datatable');
        Route::get('leave-types/{leave_type}', [LeaveController::class, 'showType'])->name('leave-types.show');
        Route::post('leave-types', [LeaveController::class, 'storeType'])->name('leave-types.store');
        Route::put('leave-types/{leave_type}', [LeaveController::class, 'updateType'])->name('leave-types.update');
        Route::delete('leave-types/{leave_type}', [LeaveController::class, 'destroyType'])->name('leave-types.destroy');
        Route::post('leave-requests', [LeaveController::class, 'store'])->name('leave-requests.store');
        Route::get('employees/{employee}/leave-balances', [LeaveController::class, 'balances'])->name('employees.leave-balances');
    });

    // HR sees every request; managers see (and decide) their own team's. The service checks who may decide.
    Route::get('leave-requests/datatable', [LeaveController::class, 'datatable'])->name('leave-requests.datatable');
    Route::post('leave-requests/{leave_request}/decide', [LeaveController::class, 'decide'])->name('leave-requests.decide');
    Route::post('leave-requests/{leave_request}/cancel', [LeaveController::class, 'cancel'])->name('leave-requests.cancel');

    Route::middleware('permission:manage-payroll')->group(function () {
        Route::get('salary-components/datatable', [PayrollController::class, 'componentsDatatable'])->name('salary-components.datatable');
        Route::get('salary-components/options', [PayrollController::class, 'components'])->name('salary-components.options');
        Route::get('salary-components/{salary_component}', [PayrollController::class, 'showComponent'])->name('salary-components.show');
        Route::post('salary-components', [PayrollController::class, 'storeComponent'])->name('salary-components.store');
        Route::put('salary-components/{salary_component}', [PayrollController::class, 'updateComponent'])->name('salary-components.update');
        Route::delete('salary-components/{salary_component}', [PayrollController::class, 'destroyComponent'])->name('salary-components.destroy');

        Route::get('tax-slabs', [PayrollController::class, 'taxSlabs'])->name('tax-slabs.index');
        Route::post('tax-slabs', [PayrollController::class, 'storeTaxSlab'])->name('tax-slabs.store');
        Route::put('tax-slabs/{tax_slab}', [PayrollController::class, 'updateTaxSlab'])->name('tax-slabs.update');
        Route::delete('tax-slabs/{tax_slab}', [PayrollController::class, 'destroyTaxSlab'])->name('tax-slabs.destroy');

        Route::get('salary-structures/datatable', [PayrollController::class, 'structuresDatatable'])->name('salary-structures.datatable');
        Route::get('employees/{employee}/salary-structure', [PayrollController::class, 'structure'])->name('employees.salary-structure');
        Route::post('employees/{employee}/salary-structure', [PayrollController::class, 'saveStructure'])->name('employees.salary-structure.store');

        Route::get('payroll-late-policy', [PayrollController::class, 'latePolicy'])->name('payroll-late-policy');
        Route::put('payroll-late-policy', [PayrollController::class, 'saveLatePolicy'])->name('payroll-late-policy.update');
        Route::get('payroll-adjustments/datatable', [PayrollController::class, 'adjustmentsDatatable'])->name('payroll-adjustments.datatable');
        Route::post('payroll-adjustments', [PayrollController::class, 'storeAdjustment'])->name('payroll-adjustments.store');
        Route::put('payroll-adjustments/{payroll_adjustment}', [PayrollController::class, 'updateAdjustment'])->name('payroll-adjustments.update');
        Route::delete('payroll-adjustments/{payroll_adjustment}', [PayrollController::class, 'destroyAdjustment'])->name('payroll-adjustments.destroy');
        Route::get('payroll-runs/datatable', [PayrollController::class, 'runsDatatable'])->name('payroll-runs.datatable');
        Route::post('payroll-runs/generate', [PayrollController::class, 'generate'])->name('payroll-runs.generate');
        Route::get('payroll-runs/{payroll_run}', [PayrollController::class, 'showRun'])->name('payroll-runs.show');
        Route::get('payroll-runs/{payroll_run}/payslips', [PayrollController::class, 'payslipsDatatable'])->name('payroll-runs.payslips');
        Route::post('payroll-runs/{payroll_run}/approve', [PayrollController::class, 'approve'])->name('payroll-runs.approve');
        Route::post('payroll-runs/{payroll_run}/post', [PayrollController::class, 'post'])->name('payroll-runs.post');
        Route::post('payroll-runs/{payroll_run}/pay', [PayrollController::class, 'pay'])->name('payroll-runs.pay');
        Route::post('payroll-runs/{payroll_run}/reverse', [PayrollController::class, 'reverse'])->name('payroll-runs.reverse');

        Route::get('employee-loans/datatable', [LoanController::class, 'datatable'])->name('employee-loans.datatable');
        Route::get('employee-loans/{employee_loan}', [LoanController::class, 'show'])->name('employee-loans.show');
        Route::post('employee-loans', [LoanController::class, 'store'])->name('employee-loans.store');
        Route::post('employee-loans/{employee_loan}/decide', [LoanController::class, 'decide'])->name('employee-loans.decide');
        Route::post('employee-loans/{employee_loan}/disburse', [LoanController::class, 'disburse'])->name('employee-loans.disburse');
    });

    Route::prefix('my-hr')->name('my-hr.')->group(function () {
        Route::get('leave', [MyHrController::class, 'leave'])->name('leave');
        Route::post('leave', [MyHrController::class, 'applyLeave'])->name('leave.store');
        Route::post('leave/{leave_request}/cancel', [MyHrController::class, 'cancelLeave'])->name('leave.cancel');
        Route::get('payslips', [MyHrController::class, 'payslips'])->name('payslips');
        Route::get('loans', [MyHrController::class, 'loans'])->name('loans');
        Route::post('loans', [MyHrController::class, 'requestLoan'])->name('loans.store');
    });

    // ---------- Students ----------
    // Admissions, students and fees (PLAN.md §5, §6, §9, §10). Money is always in paisa.

    Route::middleware('permission:manage-admissions')->group(function () {
        Route::get('admission-inquiries/datatable', [AdmissionInquiryController::class, 'datatable'])->name('admission-inquiries.datatable');
        Route::post('admission-inquiries/{admission_inquiry}/convert', [AdmissionInquiryController::class, 'convert'])->name('admission-inquiries.convert');
        Route::apiResource('admission-inquiries', AdmissionInquiryController::class);

        Route::get('admission-applications/datatable', [AdmissionApplicationController::class, 'datatable'])->name('admission-applications.datatable');
        Route::post('admission-applications/{admission_application}/status', [AdmissionApplicationController::class, 'changeStatus'])->name('admission-applications.status');
        Route::post('admission-applications/{admission_application}/tests', [AdmissionApplicationController::class, 'storeTest'])->name('admission-applications.tests.store');
        Route::put('admission-tests/{admission_test}', [AdmissionApplicationController::class, 'updateTest'])->name('admission-tests.update');
        Route::post('admission-applications/{admission_application}/interviews', [AdmissionApplicationController::class, 'storeInterview'])->name('admission-applications.interviews.store');
        Route::put('admission-interviews/{admission_interview}', [AdmissionApplicationController::class, 'updateInterview'])->name('admission-interviews.update');
        Route::post('admission-applications/{admission_application}/enrol', [AdmissionApplicationController::class, 'enrol'])->name('admission-applications.enrol');
        Route::apiResource('admission-applications', AdmissionApplicationController::class);
    });

    Route::middleware('permission:manage-students')->group(function () {
        Route::get('students/datatable', [StudentRecordController::class, 'datatable'])->name('students.datatable');
        Route::post('students/{student}/records/{type}', [StudentCareController::class, 'store'])->where('type', 'medical|discipline|achievements')->name('students.records.store');
        Route::put('student-records/{type}/{id}', [StudentCareController::class, 'update'])->where('type', 'medical|discipline|achievements')->whereNumber('id')->name('student-records.update');
        Route::delete('student-records/{type}/{id}', [StudentCareController::class, 'destroy'])->where('type', 'medical|discipline|achievements')->whereNumber('id')->name('student-records.destroy');
        Route::post('students/{student}/certificates', [StudentCareController::class, 'issueCertificate'])->name('students.certificates.store');
        Route::post('students/{student}/portal-account', [StudentRecordController::class, 'studentLogin'])->name('students.portal-account');
        Route::post('students/{student}/leaving-certificate', [StudentLeavingController::class, 'issueCertificate'])->name('students.leaving-certificate');
        Route::post('students/{student}/status', [StudentRecordController::class, 'changeStatus'])->name('students.status');
        Route::put('enrollments/{enrollment}', [StudentRecordController::class, 'updateEnrollment'])->name('enrollments.update');
        Route::get('guardians', [StudentRecordController::class, 'guardians'])->name('guardians.index');
        Route::post('students/{student}/guardians', [StudentRecordController::class, 'attachGuardian'])->name('students.guardians.store');
        Route::delete('students/{student}/guardians/{guardian}', [StudentRecordController::class, 'detachGuardian'])->name('students.guardians.destroy');
        Route::put('guardians/{guardian}', [StudentRecordController::class, 'updateGuardian'])->name('guardians.update');
        Route::post('guardians/{guardian}/portal-account', [StudentRecordController::class, 'parentLogin'])->name('guardians.portal-account');
        Route::apiResource('students', StudentRecordController::class);

        Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::get('promotions/students', [PromotionController::class, 'students'])->name('promotions.students');
        Route::post('promotions/prepare', [PromotionController::class, 'prepare'])->middleware('permission:manage-academics')->name('promotions.prepare');
        Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
        Route::post('promotions/undo', [PromotionController::class, 'undo'])->name('promotions.undo');
        Route::post('promotions/roll-numbers', [PromotionController::class, 'rollNumbers'])->name('promotions.roll-numbers');
    });

    Route::prefix('fees')->name('fees.')->group(function () {
        Route::get('heads', [FeeSetupController::class, 'heads'])->middleware('permission:manage-fee-setup,manage-fees')->name('heads.index');
        Route::get('concession-types', [FeeSetupController::class, 'concessionTypes'])->middleware('permission:manage-fee-setup,manage-fees')->name('concession-types.index');

        Route::middleware('permission:manage-fee-setup')->group(function () {
            Route::get('heads/datatable', [FeeSetupController::class, 'headsDatatable'])->name('heads.datatable');
            Route::post('heads', [FeeSetupController::class, 'storeHead'])->name('heads.store');
            Route::put('heads/{fee_head}', [FeeSetupController::class, 'updateHead'])->name('heads.update');
            Route::delete('heads/{fee_head}', [FeeSetupController::class, 'destroyHead'])->name('heads.destroy');

            Route::get('concession-types/datatable', [FeeSetupController::class, 'concessionTypesDatatable'])->name('concession-types.datatable');
            Route::post('concession-types', [FeeSetupController::class, 'storeConcessionType'])->name('concession-types.store');
            Route::put('concession-types/{concession_type}', [FeeSetupController::class, 'updateConcessionType'])->name('concession-types.update');
            Route::delete('concession-types/{concession_type}', [FeeSetupController::class, 'destroyConcessionType'])->name('concession-types.destroy');

            Route::get('structures/datatable', [FeeSetupController::class, 'structuresDatatable'])->name('structures.datatable');
            Route::post('structures/copy', [FeeSetupController::class, 'copyStructures'])->name('structures.copy');
            Route::get('structures/{fee_structure}', [FeeSetupController::class, 'showStructure'])->name('structures.show');
            Route::post('structures', [FeeSetupController::class, 'storeStructure'])->name('structures.store');
            Route::put('structures/{fee_structure}', [FeeSetupController::class, 'updateStructure'])->name('structures.update');
            Route::delete('structures/{fee_structure}', [FeeSetupController::class, 'destroyStructure'])->name('structures.destroy');

            Route::get('late-fee-rules/datatable', [FeeAutomationController::class, 'rulesDatatable'])->name('late-fee-rules.datatable');
            Route::post('late-fee-rules', [FeeAutomationController::class, 'storeRule'])->name('late-fee-rules.store');
            Route::put('late-fee-rules/{late_fee_rule}', [FeeAutomationController::class, 'updateRule'])->name('late-fee-rules.update');
            Route::delete('late-fee-rules/{late_fee_rule}', [FeeAutomationController::class, 'destroyRule'])->name('late-fee-rules.destroy');
            Route::get('automation', [FeeAutomationController::class, 'settings'])->name('automation');
            Route::put('automation', [FeeAutomationController::class, 'saveSettings'])->name('automation.update');

            Route::get('concessions/datatable', [FeeSetupController::class, 'concessionsDatatable'])->name('concessions.datatable');
            Route::post('concessions', [FeeSetupController::class, 'storeConcession'])->name('concessions.store');
            Route::post('concessions/{student_concession}/decision', [FeeSetupController::class, 'decideConcession'])->name('concessions.decision');
            Route::delete('concessions/{student_concession}', [FeeSetupController::class, 'destroyConcession'])->name('concessions.destroy');
        });

        Route::get('students', [FeeInvoiceController::class, 'students'])->middleware('permission:manage-fees,manage-fee-setup')->name('students.index');
        Route::get('invoices', [FeeInvoiceController::class, 'index'])->middleware('permission:manage-fees,view-fees')->name('invoices.index');

        Route::middleware('permission:manage-fees')->group(function () {
            Route::get('invoices/datatable', [FeeInvoiceController::class, 'datatable'])->name('invoices.datatable');
            Route::post('invoices', [FeeInvoiceController::class, 'store'])->name('invoices.store');
            Route::post('invoices/generate', [FeeInvoiceController::class, 'generate'])->name('invoices.generate');
            Route::post('invoices/{fee_invoice}/cancel', [FeeInvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::post('invoices/{fee_invoice}/waive', [FeeInvoiceController::class, 'waive'])->name('invoices.waive');

            Route::get('payments/datatable', [FeePaymentController::class, 'datatable'])->name('payments.datatable');
            Route::get('payments', [FeePaymentController::class, 'index'])->name('payments.index');
            Route::post('payments', [FeePaymentController::class, 'store'])->name('payments.store');
            Route::get('payments/{fee_payment}', [FeePaymentController::class, 'show'])->name('payments.show');
            Route::post('payments/{fee_payment}/reverse', [FeePaymentController::class, 'reverse'])->name('payments.reverse');
        });

        Route::get('invoices/{fee_invoice}', [FeeInvoiceController::class, 'show'])->middleware('permission:manage-fees,view-fees')->name('invoices.show');

        Route::middleware('permission:issue-refunds')->group(function () {
            Route::get('refunds/datatable', [StudentLeavingController::class, 'refundsDatatable'])->name('refunds.datatable');
            Route::post('refunds', [StudentLeavingController::class, 'refund'])->name('refunds.store');
            Route::post('students/{student}/adjust-deposit', [StudentLeavingController::class, 'adjustDeposit'])->name('students.adjust-deposit');
            Route::post('refunds/{fee_refund}/reverse', [StudentLeavingController::class, 'reverseRefund'])->name('refunds.reverse');
        });
    });

    // Staff with manage-students, or the child's own family (portal and mobile app).
    Route::get('students/{student}/history', [StudentRecordController::class, 'history'])->name('students.history');
    Route::get('students/{student}/records', [StudentCareController::class, 'index'])->name('students.records.index');
    Route::get('family/students', [FamilyController::class, 'index'])->name('family.students.index');
    Route::get('family/students/{student}', [FamilyController::class, 'show'])->name('family.students.show');
    Route::get('students/{student}/clearance', [StudentLeavingController::class, 'clearance'])->middleware('permission:manage-students,issue-refunds')->name('students.clearance');

    // ---------- Cards ----------
    // ID card templates, issuing and printing (PLAN.md §23). The QR check page is public (routes/web.php).

    Route::middleware('permission:manage-cards')->group(function () {
        Route::get('card-templates/datatable', [IdCardController::class, 'templatesDatatable'])->name('card-templates.datatable');
        Route::get('card-templates', [IdCardController::class, 'templates'])->name('card-templates.index');
        Route::post('card-templates', [IdCardController::class, 'storeTemplate'])->name('card-templates.store');
        Route::get('card-templates/{card_template}', [IdCardController::class, 'showTemplate'])->name('card-templates.show');
        Route::get('card-templates/{card_template}/preview', [IdCardController::class, 'previewTemplate'])->name('card-templates.preview');
        Route::put('card-templates/{card_template}', [IdCardController::class, 'updateTemplate'])->name('card-templates.update');
        Route::delete('card-templates/{card_template}', [IdCardController::class, 'destroyTemplate'])->name('card-templates.destroy');

        Route::get('id-cards/datatable', [IdCardController::class, 'datatable'])->name('id-cards.datatable');
        Route::get('id-cards/holders', [IdCardController::class, 'holders'])->name('id-cards.holders');
        Route::get('id-cards', [IdCardController::class, 'index'])->name('id-cards.index');
        Route::post('id-cards/issue', [IdCardController::class, 'issue'])->name('id-cards.issue');
        Route::post('id-cards/{id_card}/status', [IdCardController::class, 'changeStatus'])->name('id-cards.status');
    });

    Route::get('my-id-cards', [IdCardController::class, 'mine'])->name('my-id-cards.index');

    // ---------- Attendance ----------
    // Timetable, calendar, student & staff attendance, student leave (PLAN.md §4, §7, §14).

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

    Route::middleware('permission:manage-staff-attendance')->group(function () {
        Route::get('attendance-kiosks/datatable', [AttendanceKioskController::class, 'datatable'])->name('attendance-kiosks.datatable');
        Route::post('attendance-kiosks', [AttendanceKioskController::class, 'store'])->name('attendance-kiosks.store');
        Route::put('attendance-kiosks/{attendance_kiosk}', [AttendanceKioskController::class, 'update'])->name('attendance-kiosks.update');
        Route::post('attendance-kiosks/{attendance_kiosk}/renew', [AttendanceKioskController::class, 'renew'])->name('attendance-kiosks.renew');
    });
    Route::get('my-attendance-qr', [IdCardController::class, 'myQr'])->name('my-attendance-qr');
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

    // ---------- Diary ----------
    // Daily diary and homework (PLAN.md §8). Parents and students read and sign / submit; teachers write.

    Route::get('homework', [HomeworkController::class, 'index'])->name('homework.index');
    Route::post('homework/{homework}/submit', [HomeworkController::class, 'submit'])->name('homework.submit');
    Route::get('diary/students/{student}', [DiaryController::class, 'student'])->name('diary.student');
    Route::post('diaries/{diary}/acknowledge', [DiaryController::class, 'acknowledge'])->name('diaries.acknowledge');

    Route::middleware('permission:manage-diary')->group(function () {
        Route::get('diary', [DiaryController::class, 'page'])->name('diary.page');
        Route::put('diary/note', [DiaryController::class, 'note'])->name('diary.note');
        Route::post('diary/entries', [DiaryController::class, 'storeEntry'])->name('diary.entries.store');
        Route::put('diary/entries/{diary_entry}', [DiaryController::class, 'updateEntry'])->name('diary.entries.update');
        Route::delete('diary/entries/{diary_entry}', [DiaryController::class, 'destroyEntry'])->name('diary.entries.destroy');
        Route::post('diaries/{diary}/publish', [DiaryController::class, 'publish'])->name('diaries.publish');
        Route::post('diaries/{diary}/remarks', [DiaryController::class, 'storeRemark'])->name('diaries.remarks.store');

        Route::get('homework/datatable', [HomeworkController::class, 'datatable'])->name('homework.datatable');
        Route::post('homework', [HomeworkController::class, 'store'])->name('homework.store');
        Route::put('homework/{homework}', [HomeworkController::class, 'update'])->name('homework.update');
        Route::delete('homework/{homework}', [HomeworkController::class, 'destroy'])->name('homework.destroy');
        Route::get('homework/{homework}/submissions', [HomeworkController::class, 'submissions'])->name('homework.submissions');
        Route::post('homework/{homework}/grade', [HomeworkController::class, 'grade'])->name('homework.grade');
    });

    // ---------- Exams ----------
    // Exams, marks and results (PLAN.md §8). Teachers enter marks for their own subjects; the exam
    // cell (manage-exams) approves and publishes. Students and parents read published results.

    Route::get('exams/results/students/{student}', [ExamController::class, 'studentResults'])->name('exams.results.student');

    // Online MCQ tests: students attempt their own section's tests (checked in the service).
    Route::get('my-online-tests', [OnlineTestController::class, 'mine'])->name('online-tests.mine');
    Route::post('online-tests/{online_test}/start', [OnlineTestController::class, 'start'])->name('online-tests.start');
    Route::post('online-tests/{online_test}/submit', [OnlineTestController::class, 'submit'])->middleware('throttle:20,1')->name('online-tests.submit');
    Route::get('online-tests/{online_test}/review', [OnlineTestController::class, 'review'])->name('online-tests.review');

    Route::middleware('permission:submit-grades,manage-exams')->group(function () {
        Route::get('marks', [MarksController::class, 'sheet'])->name('marks.sheet');
        Route::post('marks', [MarksController::class, 'store'])->name('marks.store');
        Route::get('exams/list', [ExamController::class, 'index'])->name('exams.list');
        Route::get('exams/{exam}/summary', [ExamController::class, 'show'])->name('exams.summary');
        Route::get('online-tests/datatable', [OnlineTestController::class, 'datatable'])->name('online-tests.datatable');
        Route::post('online-tests/{online_test}/status', [OnlineTestController::class, 'changeStatus'])->name('online-tests.status');
        Route::get('online-tests/{online_test}/results', [OnlineTestController::class, 'results'])->name('online-tests.results');
        Route::apiResource('online-tests', OnlineTestController::class)->except('index');
    });

    Route::middleware('permission:manage-exams')->group(function () {
        Route::post('marks/approve', [MarksController::class, 'approve'])->name('marks.approve');

        Route::prefix('exam-setup')->name('exam-setup.')->group(function () {
            Route::get('{type}', [ExamController::class, 'setupIndex'])->whereIn('type', ['terms', 'scales', 'halls', 'skills', 'rules'])->name('index');
            Route::post('{type}', [ExamController::class, 'setupStore'])->whereIn('type', ['terms', 'scales', 'halls', 'skills', 'rules'])->name('store');
            Route::put('{type}/{id}', [ExamController::class, 'setupUpdate'])->whereIn('type', ['terms', 'scales', 'halls', 'skills', 'rules'])->whereNumber('id')->name('update');
            Route::delete('{type}/{id}', [ExamController::class, 'setupDestroy'])->whereIn('type', ['terms', 'scales', 'halls', 'skills', 'rules'])->whereNumber('id')->name('destroy');
        });

        Route::get('exams/datatable', [ExamController::class, 'datatable'])->name('exams.datatable');
        Route::put('exams/{exam}/date-sheet', [ExamController::class, 'dateSheet'])->name('exams.date-sheet');
        Route::post('exams/{exam}/status', [ExamController::class, 'changeStatus'])->name('exams.status');
        Route::post('exams/{exam}/fee-challans', [ExamController::class, 'feeChallans'])->name('exams.fee-challans');
        Route::post('exams/{exam}/admit-cards', [ExamController::class, 'issueAdmitCards'])->name('exams.admit-cards.issue');
        Route::get('exams/{exam}/admit-cards', [ExamController::class, 'admitCards'])->name('exams.admit-cards');
        Route::post('admit-cards/{admit_card}/override', [ExamController::class, 'overrideAdmitCard'])->name('admit-cards.override');
        Route::post('exam-papers/{exam_subject}/seating', [ExamController::class, 'generateSeating'])->name('exam-papers.seating.generate');
        Route::get('exam-papers/{exam_subject}/seating', [ExamController::class, 'seating'])->name('exam-papers.seating');
        Route::get('exams/{exam}/tabulation', [ExamController::class, 'tabulation'])->name('exams.tabulation');
        Route::post('exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
        Route::apiResource('exams', ExamController::class);
    });

    // ---------- Performance ----------
    // Student performance tracker (PLAN.md §20). Students and parents read their own profile.

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

    // ---------- Communication ----------
    // Announcements, the notification bell, message templates and the delivery log (PLAN.md §17).
    // Every signed-in user reads their own feed and bell; writing needs manage-announcements.

    Route::get('my-announcements', [AnnouncementController::class, 'feed'])->name('my-announcements.index');
    Route::post('my-announcements/{announcement}/read', [AnnouncementController::class, 'read'])->whereNumber('announcement')->name('my-announcements.read');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/summary', [NotificationController::class, 'summary'])->name('notifications.summary');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification')->name('notifications.read');

    Route::middleware('permission:manage-announcements')->group(function () {
        Route::get('announcements/datatable', [AnnouncementController::class, 'datatable'])->name('announcements.datatable');
        Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->whereNumber('announcement')->name('announcements.show');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::post('announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('announcements.publish');
        Route::post('announcements/{announcement}/archive', [AnnouncementController::class, 'archive'])->name('announcements.archive');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('announcement-categories/options', [AnnouncementController::class, 'categories'])->name('announcement-categories.options');
        Route::get('announcement-categories/datatable', [AnnouncementController::class, 'categoriesDatatable'])->name('announcement-categories.datatable');
        Route::get('announcement-categories/{category}', [AnnouncementController::class, 'showCategory'])->name('announcement-categories.show');
        Route::post('announcement-categories', [AnnouncementController::class, 'storeCategory'])->name('announcement-categories.store');
        Route::put('announcement-categories/{category}', [AnnouncementController::class, 'updateCategory'])->name('announcement-categories.update');
        Route::delete('announcement-categories/{category}', [AnnouncementController::class, 'destroyCategory'])->name('announcement-categories.destroy');

        Route::get('message-templates/datatable', [NotificationController::class, 'templatesDatatable'])->name('message-templates.datatable');
        Route::get('message-templates/{template}', [NotificationController::class, 'showTemplate'])->name('message-templates.show');
        Route::post('message-templates', [NotificationController::class, 'storeTemplate'])->name('message-templates.store');
        Route::put('message-templates/{template}', [NotificationController::class, 'updateTemplate'])->name('message-templates.update');
        Route::delete('message-templates/{template}', [NotificationController::class, 'destroyTemplate'])->name('message-templates.destroy');

        Route::get('notification-logs/datatable', [NotificationController::class, 'logsDatatable'])->name('notification-logs.datatable');
    });

    // ---------- Collaboration ----------
    // Phase 13: sticky notes (every user, own notes only), tasks (use-tasks) and staff chat (use-chat).

    Route::get('sticky-notes', [StickyNoteController::class, 'index'])->name('sticky-notes.index');
    Route::get('sticky-notes/colors', [StickyNoteController::class, 'colors'])->name('sticky-notes.colors');
    Route::post('sticky-notes', [StickyNoteController::class, 'store'])->name('sticky-notes.store');
    Route::put('sticky-notes/reorder', [StickyNoteController::class, 'reorder'])->name('sticky-notes.reorder');
    Route::put('sticky-notes/{note}', [StickyNoteController::class, 'update'])->whereNumber('note')->name('sticky-notes.update');
    Route::patch('sticky-notes/{note}/done', [StickyNoteController::class, 'done'])->whereNumber('note')->name('sticky-notes.done');
    Route::patch('sticky-notes/{note}/pin', [StickyNoteController::class, 'pin'])->whereNumber('note')->name('sticky-notes.pin');
    Route::patch('sticky-notes/{note}/items/{item}', [StickyNoteController::class, 'toggleItem'])->whereNumber(['note', 'item'])->name('sticky-notes.items.toggle');
    Route::delete('sticky-notes/{note}', [StickyNoteController::class, 'destroy'])->whereNumber('note')->name('sticky-notes.destroy');

    Route::middleware('permission:use-tasks')->group(function () {
        Route::get('task-boards', [TaskController::class, 'boards'])->name('task-boards.index');
        Route::get('task-boards/{board}', [TaskController::class, 'showBoard'])->name('task-boards.show');
        Route::post('task-boards', [TaskController::class, 'storeBoard'])->name('task-boards.store');
        Route::put('task-boards/{board}', [TaskController::class, 'updateBoard'])->name('task-boards.update');
        Route::delete('task-boards/{board}', [TaskController::class, 'destroyBoard'])->name('task-boards.destroy');

        Route::get('tasks/options', [TaskController::class, 'options'])->name('tasks.options');
        Route::get('tasks/datatable', [TaskController::class, 'datatable'])->name('tasks.datatable');
        Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('tasks/{task}', [TaskController::class, 'show'])->whereNumber('task')->name('tasks.show');
        Route::put('tasks/{task}', [TaskController::class, 'update'])->whereNumber('task')->name('tasks.update');
        Route::patch('tasks/{task}/move', [TaskController::class, 'move'])->whereNumber('task')->name('tasks.move');
        Route::patch('tasks/{task}/checklist/{item}', [TaskController::class, 'toggleChecklist'])->whereNumber(['task', 'item'])->name('tasks.checklist.toggle');
        Route::post('tasks/{task}/comments', [TaskController::class, 'comment'])->whereNumber('task')->name('tasks.comments.store');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->whereNumber('task')->name('tasks.destroy');
    });

    Route::middleware('permission:use-chat')->prefix('chat')->name('chat.')->group(function () {
        Route::get('conversations', [ChatController::class, 'conversations'])->name('conversations.index');
        Route::get('unread', [ChatController::class, 'unread'])->name('unread');
        Route::get('people', [ChatController::class, 'people'])->name('people');
        Route::get('search', [ChatController::class, 'search'])->name('search');
        Route::post('direct', [ChatController::class, 'direct'])->name('direct');
        Route::post('groups', [ChatController::class, 'group'])->name('groups.store');
        Route::put('conversations/{conversation}', [ChatController::class, 'updateGroup'])->whereNumber('conversation')->name('conversations.update');
        Route::post('conversations/{conversation}/leave', [ChatController::class, 'leave'])->whereNumber('conversation')->name('conversations.leave');
        Route::patch('conversations/{conversation}/preferences', [ChatController::class, 'preferences'])->whereNumber('conversation')->name('conversations.preferences');
        Route::get('conversations/{conversation}/messages', [ChatController::class, 'messages'])->whereNumber('conversation')->name('messages.index');
        Route::post('conversations/{conversation}/messages', [ChatController::class, 'send'])->whereNumber('conversation')->middleware('throttle:60,1')->name('messages.store');
        Route::post('conversations/{conversation}/read', [ChatController::class, 'read'])->whereNumber('conversation')->name('conversations.read');
        Route::put('messages/{message}', [ChatController::class, 'edit'])->whereNumber('message')->name('messages.update');
        Route::delete('messages/{message}', [ChatController::class, 'destroy'])->whereNumber('message')->name('messages.destroy');
        Route::post('messages/{message}/react', [ChatController::class, 'react'])->whereNumber('message')->name('messages.react');
        Route::post('messages/{message}/forward', [ChatController::class, 'forward'])->whereNumber('message')->name('messages.forward');
    });

    Route::middleware('permission:monitor-chat')->prefix('chat-monitor')->name('chat-monitor.')->group(function () {
        Route::get('datatable', [ChatMonitorController::class, 'datatable'])->name('datatable');
        Route::get('conversations/{conversation}', [ChatMonitorController::class, 'show'])->whereNumber('conversation')->name('show');
        Route::get('conversations/{conversation}/messages', [ChatMonitorController::class, 'messages'])->whereNumber('conversation')->name('messages');
        Route::put('conversations/{conversation}/members', [ChatMonitorController::class, 'members'])->whereNumber('conversation')->name('members');
    });

    // ---------- Assets (PLAN.md §15) ----------
    Route::get('my-assets', [AssetController::class, 'mine'])->name('my-assets');
    Route::middleware('permission:assign-assets,manage-assets')->group(function () {
        Route::get('assets/holders', [AssetController::class, 'holders'])->name('assets.holders');
        Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])->whereNumber('asset')->name('assets.assign');
        Route::post('assets/{asset}/return', [AssetController::class, 'takeBack'])->whereNumber('asset')->name('assets.return');
        Route::get('asset-assignments/datatable', [AssetController::class, 'assignmentsDatatable'])->name('asset-assignments.datatable');
        Route::get('assets/datatable', [AssetController::class, 'datatable'])->name('assets.datatable');
        Route::get('assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('assets.show');
    });
    Route::middleware('permission:manage-assets')->group(function () {
        Route::get('asset-categories/datatable', [AssetController::class, 'categoriesDatatable'])->name('asset-categories.datatable');
        Route::post('asset-categories', [AssetController::class, 'storeCategory'])->name('asset-categories.store');
        Route::put('asset-categories/{asset_category}', [AssetController::class, 'updateCategory'])->name('asset-categories.update');
        Route::delete('asset-categories/{asset_category}', [AssetController::class, 'destroyCategory'])->name('asset-categories.destroy');
        Route::get('locations/datatable', [AssetController::class, 'locationsDatatable'])->name('locations.datatable');
        Route::post('locations', [AssetController::class, 'storeLocation'])->name('locations.store');
        Route::put('locations/{location}', [AssetController::class, 'updateLocation'])->name('locations.update');
        Route::delete('locations/{location}', [AssetController::class, 'destroyLocation'])->name('locations.destroy');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::put('assets/{asset}', [AssetController::class, 'update'])->whereNumber('asset')->name('assets.update');
        Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->whereNumber('asset')->name('assets.destroy');
        Route::post('assets/{asset}/repairs', [AssetController::class, 'reportRepair'])->whereNumber('asset')->name('assets.repairs.store');
        Route::post('assets/{asset}/dispose', [AssetController::class, 'dispose'])->whereNumber('asset')->name('assets.dispose');
        Route::get('asset-maintenances/datatable', [AssetController::class, 'maintenancesDatatable'])->name('asset-maintenances.datatable');
        Route::put('asset-maintenances/{asset_maintenance}', [AssetController::class, 'updateRepair'])->name('asset-maintenances.update');
        Route::post('asset-maintenances/{asset_maintenance}/complete', [AssetController::class, 'completeRepair'])->name('asset-maintenances.complete');
        Route::get('asset-audits/datatable', [AssetController::class, 'auditsDatatable'])->name('asset-audits.datatable');
        Route::post('asset-audits', [AssetController::class, 'startAudit'])->name('asset-audits.store');
        Route::get('asset-audits/{asset_audit}', [AssetController::class, 'showAudit'])->name('asset-audits.show');
        Route::put('asset-audits/{asset_audit}', [AssetController::class, 'saveAudit'])->name('asset-audits.update');
        Route::get('asset-depreciation/datatable', [AssetController::class, 'depreciationDatatable'])->name('asset-depreciation.datatable');
        Route::get('asset-depreciation/preview', [AssetController::class, 'depreciationPreview'])->name('asset-depreciation.preview');
        Route::post('asset-depreciation/run', [AssetController::class, 'depreciationRun'])->name('asset-depreciation.run');
        Route::post('asset-depreciation/reverse', [AssetController::class, 'depreciationReverse'])->name('asset-depreciation.reverse');
        Route::get('asset-disposals/datatable', [AssetController::class, 'disposalsDatatable'])->name('asset-disposals.datatable');
    });

    // ---------- Inventory & procurement (PLAN.md §16) ----------
    Route::middleware('permission:issue-stock,manage-inventory')->group(function () {
        Route::get('inventory/items/search', [InventoryController::class, 'searchItems'])->name('inventory.items.search');
        Route::get('inventory/recipients', [InventoryController::class, 'recipients'])->name('inventory.recipients');
        Route::get('stock-issues/datatable', [InventoryController::class, 'issuesDatatable'])->name('stock-issues.datatable');
        Route::get('stock-issues/overdue', [InventoryController::class, 'overdueReturns'])->name('stock-issues.overdue');
        Route::get('stock-issues/{stock_issue}', [InventoryController::class, 'showIssue'])->name('stock-issues.show');
        Route::post('stock-issues', [InventoryController::class, 'storeIssue'])->name('stock-issues.store');
        Route::post('stock-issues/{stock_issue}/return', [InventoryController::class, 'returnIssue'])->name('stock-issues.return');
    });
    Route::middleware('permission:manage-inventory')->group(function () {
        Route::get('item-categories/datatable', [InventoryController::class, 'categoriesDatatable'])->name('item-categories.datatable');
        Route::post('item-categories', [InventoryController::class, 'storeCategory'])->name('item-categories.store');
        Route::put('item-categories/{item_category}', [InventoryController::class, 'updateCategory'])->name('item-categories.update');
        Route::delete('item-categories/{item_category}', [InventoryController::class, 'destroyCategory'])->name('item-categories.destroy');
        Route::get('inventory/items/datatable', [InventoryController::class, 'itemsDatatable'])->name('inventory.items.datatable');
        Route::get('inventory/items/{inventory_item}', [InventoryController::class, 'showItem'])->whereNumber('inventory_item')->name('inventory.items.show');
        Route::post('inventory/items', [InventoryController::class, 'storeItem'])->name('inventory.items.store');
        Route::put('inventory/items/{inventory_item}', [InventoryController::class, 'updateItem'])->whereNumber('inventory_item')->name('inventory.items.update');
        Route::delete('inventory/items/{inventory_item}', [InventoryController::class, 'destroyItem'])->whereNumber('inventory_item')->name('inventory.items.destroy');
        Route::get('stock-movements/datatable', [InventoryController::class, 'movementsDatatable'])->name('stock-movements.datatable');
        Route::get('purchase-orders/datatable', [InventoryController::class, 'ordersDatatable'])->name('purchase-orders.datatable');
        Route::get('purchase-orders/{purchase_order}', [InventoryController::class, 'showOrder'])->name('purchase-orders.show');
        Route::post('purchase-orders', [InventoryController::class, 'storeOrder'])->name('purchase-orders.store');
        Route::put('purchase-orders/{purchase_order}', [InventoryController::class, 'updateOrder'])->name('purchase-orders.update');
        Route::post('purchase-orders/{purchase_order}/approve', [InventoryController::class, 'approveOrder'])->name('purchase-orders.approve');
        Route::post('purchase-orders/{purchase_order}/cancel', [InventoryController::class, 'cancelOrder'])->name('purchase-orders.cancel');
        Route::delete('purchase-orders/{purchase_order}', [InventoryController::class, 'destroyOrder'])->name('purchase-orders.destroy');
        Route::get('goods-receipts/datatable', [InventoryController::class, 'receiptsDatatable'])->name('goods-receipts.datatable');
        Route::get('goods-receipts/{goods_receipt}', [InventoryController::class, 'showReceipt'])->name('goods-receipts.show');
        Route::post('goods-receipts', [InventoryController::class, 'receive'])->name('goods-receipts.store');
        Route::get('stock-transfers/datatable', [InventoryController::class, 'transfersDatatable'])->name('stock-transfers.datatable');
        Route::get('stock-transfers/{stock_transfer}', [InventoryController::class, 'showTransfer'])->name('stock-transfers.show');
        Route::post('stock-transfers', [InventoryController::class, 'transfer'])->name('stock-transfers.store');
        Route::get('stock-adjustments/datatable', [InventoryController::class, 'adjustmentsDatatable'])->name('stock-adjustments.datatable');
        Route::post('stock-adjustments', [InventoryController::class, 'adjust'])->name('stock-adjustments.store');
    });

    // ---------- Expenses, vendor payments, bank reconciliation (PLAN.md §11) ----------
    Route::middleware('permission:manage-expenses')->group(function () {
        Route::get('expense-categories/datatable', [ExpenseController::class, 'categoriesDatatable'])->name('expense-categories.datatable');
        Route::post('expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
        Route::put('expense-categories/{expense_category}', [ExpenseController::class, 'updateCategory'])->name('expense-categories.update');
        Route::delete('expense-categories/{expense_category}', [ExpenseController::class, 'destroyCategory'])->name('expense-categories.destroy');
        Route::get('expenses/datatable', [ExpenseController::class, 'datatable'])->name('expenses.datatable');
        Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
        Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::get('vendor-balances/datatable', [ExpenseController::class, 'vendorBalances'])->name('vendor-balances.datatable');
        Route::get('vendors/{vendor}/owed', [ExpenseController::class, 'vendorOwed'])->name('vendors.owed');
        Route::get('vendor-payments/datatable', [ExpenseController::class, 'paymentsDatatable'])->name('vendor-payments.datatable');
        Route::post('vendor-payments', [ExpenseController::class, 'payVendor'])->name('vendor-payments.store');
        Route::post('vendor-payments/{vendor_payment}/reverse', [ExpenseController::class, 'reversePayment'])->name('vendor-payments.reverse');
    });
    Route::middleware('permission:reconcile-bank')->group(function () {
        Route::get('bank-reconciliations/datatable', [ExpenseController::class, 'reconciliations'])->name('bank-reconciliations.datatable');
        Route::get('bank-reconciliations/bank-accounts', [ExpenseController::class, 'bankAccounts'])->name('bank-reconciliations.bank-accounts');
        Route::post('bank-reconciliations', [ExpenseController::class, 'startReconciliation'])->name('bank-reconciliations.store');
        Route::get('bank-reconciliations/{bank_reconciliation}', [ExpenseController::class, 'showReconciliation'])->whereNumber('bank_reconciliation')->name('bank-reconciliations.show');
        Route::put('bank-reconciliations/{bank_reconciliation}', [ExpenseController::class, 'saveReconciliation'])->whereNumber('bank_reconciliation')->name('bank-reconciliations.update');
        Route::post('bank-reconciliations/{bank_reconciliation}/complete', [ExpenseController::class, 'completeReconciliation'])->whereNumber('bank_reconciliation')->name('bank-reconciliations.complete');
        Route::delete('bank-reconciliations/{bank_reconciliation}', [ExpenseController::class, 'destroyReconciliation'])->whereNumber('bank_reconciliation')->name('bank-reconciliations.destroy');
    });

    // ---------- Reports centre (PLAN.md phase 17e): each report checks its module's permission ----------
    // CSV import of students, staff and opening fee balances (each type checks its own permission).
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports/{type}', [ImportController::class, 'store'])->where('type', '[a-z_]+')->middleware('throttle:20,1')->name('imports.store');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z-]+')->name('reports.show');

    // ---------- Counselling (PLAN.md §20) ----------
    Route::middleware('permission:manage-counselling')->group(function () {
        Route::get('counselling-notes/datatable', [CounsellingController::class, 'datatable'])->name('counselling-notes.datatable');
        Route::get('counselling-counsellors', [CounsellingController::class, 'counsellors'])->name('counselling-counsellors');
        Route::get('counselling-students', [CounsellingController::class, 'students'])->name('counselling-students');
        Route::get('counselling-notes/{counselling_note}', [CounsellingController::class, 'show'])->whereNumber('counselling_note')->name('counselling-notes.show');
        Route::post('counselling-notes', [CounsellingController::class, 'store'])->name('counselling-notes.store');
        Route::put('counselling-notes/{counselling_note}', [CounsellingController::class, 'update'])->whereNumber('counselling_note')->name('counselling-notes.update');
        Route::delete('counselling-notes/{counselling_note}', [CounsellingController::class, 'destroy'])->whereNumber('counselling_note')->name('counselling-notes.destroy');
        Route::post('counselling-notes/{counselling_note}/follow-up-done', [CounsellingController::class, 'completeFollowUp'])->whereNumber('counselling_note')->name('counselling-notes.follow-up-done');
    });

    // ---------- Staff appraisals (PLAN.md §14.5) ----------
    Route::get('my-appraisals', [AppraisalController::class, 'mine'])->name('my-appraisals');
    Route::get('appraisals/{appraisal}', [AppraisalController::class, 'show'])->whereNumber('appraisal')->name('appraisals.show');
    Route::put('appraisals/{appraisal}/self-review', [AppraisalController::class, 'selfReview'])->whereNumber('appraisal')->name('appraisals.self-review');
    Route::put('appraisals/{appraisal}/review', [AppraisalController::class, 'review'])->whereNumber('appraisal')->name('appraisals.review');
    Route::post('appraisals/{appraisal}/acknowledge', [AppraisalController::class, 'acknowledge'])->whereNumber('appraisal')->name('appraisals.acknowledge');
    Route::middleware('permission:manage-appraisals')->group(function () {
        Route::get('appraisal-options', [AppraisalController::class, 'options'])->name('appraisal-options');
        Route::get('appraisal-criteria/datatable', [AppraisalController::class, 'criteriaDatatable'])->name('appraisal-criteria.datatable');
        Route::post('appraisal-criteria', [AppraisalController::class, 'storeCriterion'])->name('appraisal-criteria.store');
        Route::put('appraisal-criteria/{appraisal_criterion}', [AppraisalController::class, 'updateCriterion'])->name('appraisal-criteria.update');
        Route::delete('appraisal-criteria/{appraisal_criterion}', [AppraisalController::class, 'destroyCriterion'])->name('appraisal-criteria.destroy');
        Route::get('appraisal-cycles/datatable', [AppraisalController::class, 'cyclesDatatable'])->name('appraisal-cycles.datatable');
        Route::get('appraisal-cycles/{appraisal_cycle}', [AppraisalController::class, 'showCycle'])->whereNumber('appraisal_cycle')->name('appraisal-cycles.show');
        Route::post('appraisal-cycles', [AppraisalController::class, 'storeCycle'])->name('appraisal-cycles.store');
        Route::put('appraisal-cycles/{appraisal_cycle}', [AppraisalController::class, 'updateCycle'])->whereNumber('appraisal_cycle')->name('appraisal-cycles.update');
        Route::delete('appraisal-cycles/{appraisal_cycle}', [AppraisalController::class, 'destroyCycle'])->whereNumber('appraisal_cycle')->name('appraisal-cycles.destroy');
        Route::post('appraisal-cycles/{appraisal_cycle}/launch', [AppraisalController::class, 'launch'])->whereNumber('appraisal_cycle')->name('appraisal-cycles.launch');
        Route::post('appraisal-cycles/{appraisal_cycle}/close', [AppraisalController::class, 'close'])->whereNumber('appraisal_cycle')->name('appraisal-cycles.close');
        Route::get('appraisals/datatable', [AppraisalController::class, 'appraisalsDatatable'])->name('appraisals.datatable');
        Route::get('appraisal-reviewers', [AppraisalController::class, 'reviewers'])->name('appraisal-reviewers');
        Route::put('appraisals/{appraisal}/reviewer', [AppraisalController::class, 'reassign'])->whereNumber('appraisal')->name('appraisals.reviewer');
        Route::post('appraisals/{appraisal}/reopen', [AppraisalController::class, 'reopen'])->whereNumber('appraisal')->name('appraisals.reopen');
    });

    // ---------- Transport (PLAN.md §18) ----------
    Route::get('my-bus', [TransportController::class, 'myBus'])->name('my-bus');
    Route::middleware('permission:manage-transport')->group(function () {
        Route::get('vehicles/datatable', [TransportController::class, 'vehiclesDatatable'])->name('vehicles.datatable');
        Route::post('vehicles', [TransportController::class, 'storeVehicle'])->name('vehicles.store');
        Route::put('vehicles/{vehicle}', [TransportController::class, 'updateVehicle'])->name('vehicles.update');
        Route::delete('vehicles/{vehicle}', [TransportController::class, 'destroyVehicle'])->name('vehicles.destroy');
        Route::get('transport-routes/datatable', [TransportController::class, 'routesDatatable'])->name('transport-routes.datatable');
        Route::get('transport-routes/{transport_route}', [TransportController::class, 'showRoute'])->whereNumber('transport_route')->name('transport-routes.show');
        Route::post('transport-routes', [TransportController::class, 'storeRoute'])->name('transport-routes.store');
        Route::put('transport-routes/{transport_route}', [TransportController::class, 'updateRoute'])->whereNumber('transport_route')->name('transport-routes.update');
        Route::delete('transport-routes/{transport_route}', [TransportController::class, 'destroyRoute'])->whereNumber('transport_route')->name('transport-routes.destroy');
        Route::get('transport-stops', [TransportController::class, 'stops'])->name('transport-stops.index');
        Route::get('transport-students', [TransportController::class, 'students'])->name('transport-students.index');
        Route::get('student-transport/datatable', [TransportController::class, 'ridersDatatable'])->name('student-transport.datatable');
        Route::post('student-transport', [TransportController::class, 'assign'])->name('student-transport.store');
        Route::post('student-transport/{student_transport}/end', [TransportController::class, 'end'])->name('student-transport.end');
    });

    // ---------- Admin ----------
    Route::prefix('admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->middleware('permission:view-stats')->name('admin.dashboard');
        Route::get('stats', [AdminController::class, 'stats'])->middleware('permission:view-stats')->name('admin.stats');
        Route::get('finance', [AdminController::class, 'finance'])->middleware('permission:view-stats')->name('admin.finance');
        Route::get('settings', [SystemSettingsController::class, 'show'])->middleware('permission:manage-settings')->name('admin.settings');
        Route::put('settings', [SystemSettingsController::class, 'update'])->middleware('permission:manage-settings')->name('admin.settings.update');
        Route::get('backups', [BackupController::class, 'index'])->middleware('permission:manage-settings')->name('admin.backups.index');
        Route::post('backups', [BackupController::class, 'store'])->middleware(['permission:manage-settings', 'throttle:5,1'])->name('admin.backups.store');
        Route::get('reference/countries', [SystemSettingsController::class, 'countries'])->middleware('permission:manage-settings')->name('admin.reference.countries');
        Route::get('reference/regions', [SystemSettingsController::class, 'regions'])->middleware('permission:manage-settings')->name('admin.reference.regions');
        Route::get('reference/currencies', [SystemSettingsController::class, 'currencies'])->middleware('permission:manage-settings')->name('admin.reference.currencies');
    });

    // ---------- Teacher ----------
    Route::prefix('teacher')->group(function () {
        Route::get('dashboard', [TeacherController::class, 'dashboard'])->middleware('permission:manage-classes,record-attendance,submit-grades')->name('teacher.dashboard');
        Route::get('classes', [TeacherController::class, 'classes'])->middleware('permission:manage-classes')->name('teacher.classes');
    });

    // ---------- Student ----------
    Route::prefix('student')->group(function () {
        Route::get('dashboard', [StudentController::class, 'dashboard'])->middleware('permission:view-courses,view-attendance,view-grades')->name('student.dashboard');
        Route::get('courses', [StudentController::class, 'courses'])->middleware('permission:view-courses')->name('student.courses');
        Route::get('attendance', [StudentController::class, 'attendance'])->middleware('permission:view-attendance')->name('student.attendance');
        Route::get('grades', [StudentController::class, 'grades'])->middleware('permission:view-grades')->name('student.grades');
    });

    // ---------- Parent ----------
    Route::prefix('parent')->group(function () {
        Route::get('dashboard', [ParentController::class, 'dashboard'])->middleware('permission:view-children,view-fees,view-attendance,view-grades,view-notices')->name('parent.dashboard');
        Route::get('children', [ParentController::class, 'children'])->middleware('permission:view-children')->name('parent.children');
        Route::get('fees', [ParentController::class, 'fees'])->middleware('permission:view-fees')->name('parent.fees');
        Route::get('notices', [ParentController::class, 'notices'])->middleware('permission:view-notices')->name('parent.notices');
    });

    // ---------- Accounts ----------
    Route::prefix('accounts')->group(function () {
        Route::get('dashboard', [AccountsController::class, 'dashboard'])->middleware('permission:manage-fees,view-payroll,view-fees')->name('accounts.dashboard');
    });
});
