<?php

use App\Http\Controllers\ERP\ExamController;
use App\Http\Controllers\ERP\MarksController;
use Illuminate\Support\Facades\Route;

/*
| Exams, marks and results (PLAN.md §8). Teachers enter marks for their own subjects; the exam
| cell (manage-exams) approves and publishes. Students and parents read published results.
*/

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
    Route::get('exams/{exam}/admit-cards/pdf', [ExamController::class, 'admitCardsPdf'])->name('exams.admit-cards.pdf');
    Route::post('admit-cards/{admit_card}/override', [ExamController::class, 'overrideAdmitCard'])->name('admit-cards.override');
    Route::post('exam-papers/{exam_subject}/seating', [ExamController::class, 'generateSeating'])->name('exam-papers.seating.generate');
    Route::get('exam-papers/{exam_subject}/seating', [ExamController::class, 'seating'])->name('exam-papers.seating');
    Route::get('exams/{exam}/tabulation', [ExamController::class, 'tabulation'])->name('exams.tabulation');
    Route::post('exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
    Route::get('exams/{exam}/report-cards', [ExamController::class, 'reportCardsPdf'])->name('exams.report-cards');
    Route::apiResource('exams', ExamController::class);
});
