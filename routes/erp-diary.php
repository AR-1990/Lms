<?php

use App\Http\Controllers\ERP\DiaryController;
use App\Http\Controllers\ERP\HomeworkController;
use Illuminate\Support\Facades\Route;

/*
| Daily diary and homework (PLAN.md §8). Parents and students read and sign / submit; teachers write.
*/

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
