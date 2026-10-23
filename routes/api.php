<?php

use App\Http\Controllers\ERP\AcademicSessionController;
use App\Http\Controllers\ERP\AccountController;
use App\Http\Controllers\ERP\AccountsController;
use App\Http\Controllers\ERP\ActivityLogController;
use App\Http\Controllers\ERP\AdminController;
use App\Http\Controllers\ERP\AdmissionApplicationController;
use App\Http\Controllers\ERP\AdmissionInquiryController;
use App\Http\Controllers\ERP\AnnouncementController;
use App\Http\Controllers\ERP\ApprovalController;
use App\Http\Controllers\ERP\AttachmentController;
use App\Http\Controllers\ERP\AttendanceController;
use App\Http\Controllers\ERP\AuthController;
use App\Http\Controllers\ERP\BankAccountController;
use App\Http\Controllers\ERP\CalendarController;
use App\Http\Controllers\ERP\CampusController;
use App\Http\Controllers\ERP\ChatController;
use App\Http\Controllers\ERP\ChatMonitorController;
use App\Http\Controllers\ERP\CurriculumController;
use App\Http\Controllers\ERP\DepartmentController;
use App\Http\Controllers\ERP\DesignationController;
use App\Http\Controllers\ERP\DeviceController;
use App\Http\Controllers\ERP\DiaryController;
use App\Http\Controllers\ERP\EmployeeController;
use App\Http\Controllers\ERP\ExamController;
use App\Http\Controllers\ERP\FeeInvoiceController;
use App\Http\Controllers\ERP\FeePaymentController;
use App\Http\Controllers\ERP\FeeSetupController;
use App\Http\Controllers\ERP\FiscalYearController;
use App\Http\Controllers\ERP\HomeworkController;
use App\Http\Controllers\ERP\IdCardController;
use App\Http\Controllers\ERP\JobApplicationController;
use App\Http\Controllers\ERP\JobOpeningController;
use App\Http\Controllers\ERP\JournalEntryController;
use App\Http\Controllers\ERP\LeaveController;
use App\Http\Controllers\ERP\LoanController;
use App\Http\Controllers\ERP\LookupController;
use App\Http\Controllers\ERP\MarksController;
use App\Http\Controllers\ERP\MyHrController;
use App\Http\Controllers\ERP\NotificationController;
use App\Http\Controllers\ERP\NumberSequenceController;
use App\Http\Controllers\ERP\ParentController;
use App\Http\Controllers\ERP\PaymentMethodController;
use App\Http\Controllers\ERP\PayrollController;
use App\Http\Controllers\ERP\PerformanceController;
use App\Http\Controllers\ERP\PeriodController;
use App\Http\Controllers\ERP\PermissionController;
use App\Http\Controllers\ERP\PurchaseRequestController;
use App\Http\Controllers\ERP\RoleController;
use App\Http\Controllers\ERP\SchoolClassController;
use App\Http\Controllers\ERP\SectionController;
use App\Http\Controllers\ERP\StickyNoteController;
use App\Http\Controllers\ERP\StudentController;
use App\Http\Controllers\ERP\StudentRecordController;
use App\Http\Controllers\ERP\SubjectController;
use App\Http\Controllers\ERP\SystemSettingsController;
use App\Http\Controllers\ERP\TaskController;
use App\Http\Controllers\ERP\TeacherAssignmentController;
use App\Http\Controllers\ERP\TeacherController;
use App\Http\Controllers\ERP\TimetableController;
use App\Http\Controllers\ERP\UserController;
use App\Http\Controllers\ERP\VendorController;
use App\Http\Controllers\ERP\VoucherTypeController;
use App\Http\Controllers\ERP\WebsiteContentController;
use App\Http\Controllers\ERP\WebsitePageController;
use App\Http\Controllers\SchoolController;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

/*
| Every API route lives in this file: the web admin panel, the portals and the Android/iOS apps all call it.
| Laravel adds the /api prefix and the api middleware. Blade pages live in routes/web.php; PDFs, print
| sheets, reports, exports and file links live in routes/export.php.
*/

// Public website forms (the Blade page sends its CSRF token with the request).
Route::post('inquiry', [SchoolController::class, 'submitInquiry'])->middleware([ForceJsonResponse::class, 'throttle:10,1'])->name('inquiry.submit');

// Sign in / out for the mobile apps and API clients.
Route::middleware(ForceJsonResponse::class)->name('api.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

// ERP API (/api/erp/...): signed in with the web session or a Sanctum token, then checked per permission.
Route::middleware([ForceJsonResponse::class, 'auth:sanctum'])->prefix('erp')->name('erp.')->group(function () {
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
        Route::post('students/{student}/status', [StudentRecordController::class, 'changeStatus'])->name('students.status');
        Route::put('enrollments/{enrollment}', [StudentRecordController::class, 'updateEnrollment'])->name('enrollments.update');
        Route::get('guardians', [StudentRecordController::class, 'guardians'])->name('guardians.index');
        Route::post('students/{student}/guardians', [StudentRecordController::class, 'attachGuardian'])->name('students.guardians.store');
        Route::delete('students/{student}/guardians/{guardian}', [StudentRecordController::class, 'detachGuardian'])->name('students.guardians.destroy');
        Route::put('guardians/{guardian}', [StudentRecordController::class, 'updateGuardian'])->name('guardians.update');
        Route::post('guardians/{guardian}/portal-account', [StudentRecordController::class, 'parentLogin'])->name('guardians.portal-account');
        Route::apiResource('students', StudentRecordController::class);
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
    });

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

    Route::middleware('permission:submit-grades,manage-exams')->group(function () {
        Route::get('marks', [MarksController::class, 'sheet'])->name('marks.sheet');
        Route::post('marks', [MarksController::class, 'store'])->name('marks.store');
        Route::get('exams/list', [ExamController::class, 'index'])->name('exams.list');
        Route::get('exams/{exam}/summary', [ExamController::class, 'show'])->name('exams.summary');
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

    // ---------- Admin ----------
    Route::prefix('admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->middleware('permission:view-stats')->name('admin.dashboard');
        Route::get('stats', [AdminController::class, 'stats'])->middleware('permission:view-stats')->name('admin.stats');
        Route::get('settings', [SystemSettingsController::class, 'show'])->middleware('permission:manage-settings')->name('admin.settings');
        Route::put('settings', [SystemSettingsController::class, 'update'])->middleware('permission:manage-settings')->name('admin.settings.update');
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
