<?php

use App\Http\Controllers\ERP\ActivityLogController;
use App\Http\Controllers\ERP\AttachmentController;
use App\Http\Controllers\ERP\CampusController;
use App\Http\Controllers\ERP\DeviceController;
use App\Http\Controllers\ERP\LookupController;
use App\Http\Controllers\ERP\NumberSequenceController;
use Illuminate\Support\Facades\Route;

Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
Route::put('devices/push-token', [DeviceController::class, 'updatePushToken'])->name('devices.push-token');
Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

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
