<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = Company::factory()->create(['slug' => 'acme']);
    $this->owner = User::factory()->owner()->for($this->company)->create();
    $this->client = Client::factory()->for($this->company)->create();
    $this->globex = Company::factory()->create(['slug' => 'globex']);
});

test('the API needs a token', function () {
    $this->getJson(route('api.invoices.index'))->assertUnauthorized();
});

test('a token lists and reads only invoices of its company', function () {
    $own = Invoice::factory()->sent()->for($this->company)->for($this->client)->create(['number' => 'ACME/2026/001']);
    $foreign = Invoice::factory()->sent()->for($this->globex)->create(['number' => 'GLOBEX/2026/001']);

    Sanctum::actingAs($this->owner);

    $this->getJson(route('api.invoices.index'))->assertOk()->assertJsonPath('data.0.number', 'ACME/2026/001')->assertJsonCount(1, 'data');
    $this->getJson(route('api.invoices.show', $own))->assertOk();
    $this->getJson(route('api.invoices.show', $foreign))->assertForbidden();
});

test('an invoice is created with its items through the API', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(route('api.invoices.store'), [
        'client_id' => $this->client->id,
        'issue_date' => '2026-10-17',
        'due_date' => '2026-10-31',
        'items' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 25_000]],
    ])->assertCreated()->assertJsonPath('total', 50_000)->assertJsonPath('status', 'draft');
});

test('the client must belong to the company of the token', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(route('api.invoices.store'), [
        'client_id' => Client::factory()->for($this->globex)->create()->id,
        'issue_date' => '2026-10-17',
        'due_date' => '2026-10-31',
    ])->assertUnprocessable()->assertJsonValidationErrors('client_id');
});

test('DVLA-019: extra fields in the request set the status and the company of the invoice', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(route('api.invoices.store'), [
        'client_id' => $this->client->id,
        'issue_date' => '2026-10-17',
        'due_date' => '2026-10-31',
        'status' => 'paid',
        'company_id' => $this->globex->id,
    ])->assertCreated();

    $invoice = Invoice::latest('id')->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->company_id)->toBe($this->globex->id);
});
