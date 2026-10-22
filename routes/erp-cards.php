<?php

use App\Http\Controllers\ERP\IdCardController;
use Illuminate\Support\Facades\Route;

/*
| ID card templates, issuing and printing (PLAN.md §23). The QR check page is public (routes/web.php).
*/

Route::middleware('permission:manage-cards')->group(function () {
    Route::get('card-templates/datatable', [IdCardController::class, 'templatesDatatable'])->name('card-templates.datatable');
    Route::get('card-templates', [IdCardController::class, 'templates'])->name('card-templates.index');
    Route::post('card-templates', [IdCardController::class, 'storeTemplate'])->name('card-templates.store');
    Route::get('card-templates/{card_template}', [IdCardController::class, 'showTemplate'])->name('card-templates.show');
    Route::get('card-templates/{card_template}/preview', [IdCardController::class, 'previewTemplate'])->name('card-templates.preview');
    Route::get('card-templates/{card_template}/print', [IdCardController::class, 'printTemplate'])->name('card-templates.print');
    Route::put('card-templates/{card_template}', [IdCardController::class, 'updateTemplate'])->name('card-templates.update');
    Route::delete('card-templates/{card_template}', [IdCardController::class, 'destroyTemplate'])->name('card-templates.destroy');

    Route::get('id-cards/datatable', [IdCardController::class, 'datatable'])->name('id-cards.datatable');
    Route::get('id-cards/print', [IdCardController::class, 'print'])->name('id-cards.print');
    Route::get('id-cards/holders', [IdCardController::class, 'holders'])->name('id-cards.holders');
    Route::get('id-cards', [IdCardController::class, 'index'])->name('id-cards.index');
    Route::post('id-cards/issue', [IdCardController::class, 'issue'])->name('id-cards.issue');
    Route::post('id-cards/{id_card}/status', [IdCardController::class, 'changeStatus'])->name('id-cards.status');
});

Route::get('my-id-cards', [IdCardController::class, 'mine'])->name('my-id-cards.index');
Route::get('my-id-cards/{id_card}/download', [IdCardController::class, 'downloadMine'])->whereNumber('id_card')->name('my-id-cards.download');
