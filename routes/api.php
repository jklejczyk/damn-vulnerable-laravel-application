<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientInvoiceController;
use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

/*
 * Accounting integration API, authenticated with the company owner's Sanctum token.
 */
Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    // DVLA-021: no authorization in InvoiceController@update.
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');

    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');

    // DVLA-022: not scoped.
    Route::get('clients/{client}/invoices/{invoice}', [ClientInvoiceController::class, 'show'])->name('clients.invoices.show');

    // DVLA-SAFE-005: scoped by the custom key and by scopeBindings().
    Route::get('clients/{client}/invoices/by-number/{invoice:number}', [ClientInvoiceController::class, 'showByNumber'])->where('invoice', '.+')->name('clients.invoices.by-number');
    Route::scopeBindings()->group(function () {
        Route::get('clients/{client}/invoices/{invoice}/items', [ClientInvoiceController::class, 'items'])->name('clients.invoices.items');
    });
});

// DVLA-020: outside auth:sanctum, "for the draft clean-up script".
Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('api.invoices.destroy');
