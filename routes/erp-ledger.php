<?php

use App\Http\Controllers\ERP\AccountController;
use App\Http\Controllers\ERP\BankAccountController;
use App\Http\Controllers\ERP\FiscalYearController;
use App\Http\Controllers\ERP\JournalEntryController;
use App\Http\Controllers\ERP\PaymentMethodController;
use App\Http\Controllers\ERP\VendorController;
use App\Http\Controllers\ERP\VoucherTypeController;
use Illuminate\Support\Facades\Route;

/*
| Accounts & general ledger (PLAN.md §10–11). Amounts are always in paisa.
*/

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
