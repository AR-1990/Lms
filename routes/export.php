<?php

use App\Http\Controllers\ERP\AppraisalController;
use App\Http\Controllers\ERP\AttachmentController;
use App\Http\Controllers\ERP\ExamController;
use App\Http\Controllers\ERP\IdCardController;
use App\Http\Controllers\ERP\MyHrController;
use App\Http\Controllers\ERP\PayrollController;
use App\Http\Controllers\ERP\ReportController;
use App\Http\Controllers\ERP\TransportController;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

/*
| Every file a user downloads, prints or exports: PDFs, print sheets, reports and private file links.
| Blade pages live in routes/web.php and the JSON API in routes/api.php. The ERP exports keep their
| /api/erp URLs and erp.* names, so the web screens and the mobile apps use the same links.
*/

// Short-lived signed links to private files (web and mobile app); the signature is the permission.
Route::middleware(['web', 'signed', 'throttle:60,1'])
    ->get('/files/{attachment}', [AttachmentController::class, 'download'])
    ->name('attachments.download');

Route::middleware(['api', ForceJsonResponse::class, 'auth:sanctum'])->prefix('api/erp')->name('erp.')->group(function () {
    // ID cards (PLAN.md §23)
    Route::middleware('permission:manage-cards')->group(function () {
        Route::get('card-templates/{card_template}/print', [IdCardController::class, 'printTemplate'])->name('card-templates.print');
        Route::get('id-cards/print', [IdCardController::class, 'print'])->name('id-cards.print');
    });
    Route::get('my-id-cards/{id_card}/download', [IdCardController::class, 'downloadMine'])->whereNumber('id_card')->name('my-id-cards.download');

    // Exams (PLAN.md §8)
    Route::middleware('permission:manage-exams')->group(function () {
        Route::get('exams/{exam}/admit-cards/pdf', [ExamController::class, 'admitCardsPdf'])->name('exams.admit-cards.pdf');
        Route::get('exams/{exam}/report-cards', [ExamController::class, 'reportCardsPdf'])->name('exams.report-cards');
    });

    // Transport (PLAN.md §18)
    Route::get('transport-routes/{transport_route}/sheet', [TransportController::class, 'routeSheet'])->middleware('permission:manage-transport')->name('transport-routes.sheet');

    // Reports centre (PLAN.md phase 17e): each report checks its module's permission
    Route::get('reports/{report}/csv', [ReportController::class, 'csv'])->where('report', '[a-z-]+')->name('reports.csv');
    Route::get('reports/{report}/pdf', [ReportController::class, 'pdf'])->where('report', '[a-z-]+')->name('reports.pdf');

    // Staff appraisals (PLAN.md §14.5): the employee, their reviewer or HR
    Route::get('appraisals/{appraisal}/pdf', [AppraisalController::class, 'pdf'])->whereNumber('appraisal')->name('appraisals.pdf');

    // Payroll (PLAN.md §14)
    Route::get('payroll-runs/{payroll_run}/pdf', [PayrollController::class, 'pdf'])->middleware('permission:manage-payroll')->name('payroll-runs.pdf');
    Route::get('my-hr/payslips/{payslip}/pdf', [MyHrController::class, 'payslipPdf'])->name('my-hr.payslips.pdf');
});
