<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->acme = Company::factory()->create(['slug' => 'acme']);
    $this->globex = Company::factory()->create(['slug' => 'globex']);
    $this->acmeClient = Client::factory()->for($this->acme)->create();
    $this->globexInvoice = Invoice::factory()->draft()->withItems()->for($this->globex)->create(['number' => 'GLOBEX/2026/004']);
});

test('DVLA-020: anyone deletes a draft through the API without a token', function () {
    $this->deleteJson(route('api.invoices.destroy', $this->globexInvoice))->assertNoContent();

    expect(Invoice::find($this->globexInvoice->id))->toBeNull();
});

test('DVLA-021: a token of one company changes an invoice of another', function () {
    Sanctum::actingAs(User::factory()->member()->for($this->acme)->create());

    $this->putJson(route('api.invoices.update', $this->globexInvoice), ['notes' => 'Changed by Acme'])->assertOk();

    expect($this->globexInvoice->fresh()->notes)->toBe('Changed by Acme');
});

test('DVLA-022: an invoice of another company is read through an own client', function () {
    Sanctum::actingAs(User::factory()->member()->for($this->acme)->create());

    $this->getJson(route('api.clients.invoices.show', [$this->acmeClient, $this->globexInvoice]))
        ->assertOk()
        ->assertJsonPath('data.number', 'GLOBEX/2026/004');
});

test('DVLA-SAFE-004: the API checks the policy when an invoice is read', function () {
    Sanctum::actingAs(User::factory()->member()->for($this->acme)->create());

    $this->getJson(route('api.invoices.show', $this->globexInvoice))->assertForbidden();
});

test('DVLA-SAFE-005: scoped bindings refuse an invoice of another client', function () {
    $own = Invoice::factory()->sent()->withItems()->for($this->acme)->for($this->acmeClient)->create(['number' => 'ACME/2026/002']);

    Sanctum::actingAs(User::factory()->member()->for($this->acme)->create());

    $this->getJson(route('api.clients.invoices.by-number', [$this->acmeClient, 'GLOBEX/2026/004']))->assertNotFound();
    $this->getJson(route('api.clients.invoices.items', [$this->acmeClient, $this->globexInvoice]))->assertNotFound();

    $this->getJson(route('api.clients.invoices.by-number', [$this->acmeClient, 'ACME/2026/002']))->assertOk()->assertJsonPath('data.id', $own->id);
    $this->getJson(route('api.clients.invoices.items', [$this->acmeClient, $own]))->assertOk();
});

test('DVLA-023: "invoices/*" is excluded from CSRF and production sends the session cookie cross-site', function () {
    $excluded = (fn () => static::$neverVerify)->bindTo(null, PreventRequestForgery::class)();

    expect($excluded)->toContain('invoices/*')
        ->and(file_get_contents(base_path('.env.production')))->toContain('SESSION_SAME_SITE=none');
});

test('deleting through the API still refuses invoices that are not drafts', function () {
    $sent = Invoice::factory()->sent()->for($this->globex)->create();

    $this->deleteJson(route('api.invoices.destroy', $sent))->assertUnprocessable();

    expect($sent->fresh()->status)->toBe(InvoiceStatus::Sent);
});
