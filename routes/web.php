<?php

use App\Http\Controllers\Admin\OperatedCompanyController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\InvoiceSendController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('webhooks/payments', PaymentWebhookController::class)->name('webhooks.payments');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');

    Route::livewire('invoices', 'pages::invoices.index')->name('invoices.index');
    Route::livewire('invoices/create', 'pages::invoices.form')->name('invoices.create');
    Route::livewire('invoices/{invoice}', 'pages::invoices.show')->name('invoices.show');
    Route::livewire('invoices/{invoice}/edit', 'pages::invoices.form')->name('invoices.edit');
    Route::get('invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');
    Route::post('invoices/{invoice}/send', InvoiceSendController::class)->name('invoices.send');

    Route::livewire('clients', 'pages::clients.index')->name('clients.index');
    Route::livewire('clients/create', 'pages::clients.form')->name('clients.create');
    Route::livewire('clients/{client}', 'pages::clients.show')->name('clients.show');
    Route::livewire('clients/{client}/edit', 'pages::clients.form')->name('clients.edit');

    Route::livewire('payments', 'pages::payments.index')->name('payments.index');

    Route::livewire('team', 'pages::team.index')->name('team.index');
    Route::livewire('company/settings', 'pages::company.settings')->name('company.settings');
    Route::livewire('api-tokens', 'pages::api-tokens.index')->name('api-tokens.index');

    Route::middleware('can:operate')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('companies', 'pages::admin.companies')->name('companies');
        Route::livewire('companies/{company}', 'pages::admin.company')->name('company');
        Route::livewire('users', 'pages::admin.users')->name('users');
        Route::livewire('invoices', 'pages::admin.invoices')->name('invoices');
        Route::livewire('clients', 'pages::admin.clients')->name('clients');
        Route::livewire('payments', 'pages::admin.payments')->name('payments');
        Route::post('companies/{company}/operate', [OperatedCompanyController::class, 'store'])->name('operate');
        Route::delete('operate', [OperatedCompanyController::class, 'destroy'])->name('operate.stop');
    });
});

require __DIR__.'/settings.php';
