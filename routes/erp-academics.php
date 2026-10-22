<?php

use App\Http\Controllers\ERP\AcademicSessionController;
use App\Http\Controllers\ERP\CurriculumController;
use App\Http\Controllers\ERP\PeriodController;
use App\Http\Controllers\ERP\SchoolClassController;
use App\Http\Controllers\ERP\SectionController;
use App\Http\Controllers\ERP\SubjectController;
use Illuminate\Support\Facades\Route;

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
