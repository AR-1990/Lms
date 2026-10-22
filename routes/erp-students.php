<?php

use App\Http\Controllers\ERP\AdmissionApplicationController;
use App\Http\Controllers\ERP\AdmissionInquiryController;
use App\Http\Controllers\ERP\FeeInvoiceController;
use App\Http\Controllers\ERP\FeePaymentController;
use App\Http\Controllers\ERP\FeeSetupController;
use App\Http\Controllers\ERP\StudentRecordController;
use Illuminate\Support\Facades\Route;

/*
| Admissions, students and fees (PLAN.md §5, §6, §9, §10). Money is always in paisa.
*/

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
