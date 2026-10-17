<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create(['slug' => 'acme']);
    $this->accountant = User::factory()->accountant()->for($this->company)->create();
    $this->member = User::factory()->member()->for($this->company)->create();
    $this->client = Client::factory()->for($this->company)->create(['name' => 'Wayne Enterprises']);
    $this->foreign = Invoice::factory()->sent()->withItems()->create(['number' => 'GLOBEX/2026/001']);
});

test('the list shows only invoices of the user company', function () {
    $own = Invoice::factory()->sent()->for($this->company)->for($this->client)->create(['number' => 'ACME/2026/001']);

    $this->actingAs($this->member);

    Livewire::test('pages::invoices.index')
        ->assertSee($own->number)
        ->assertDontSee($this->foreign->number);
});

test('the list filters by status and searches by client', function () {
    Invoice::factory()->paid()->for($this->company)->for($this->client)->create(['number' => 'ACME/2026/001']);
    Invoice::factory()->draft()->for($this->company)->create(['number' => 'ACME/2026/002', 'client_id' => Client::factory()->for($this->company)->create(['name' => 'Hooli'])->id]);

    $this->actingAs($this->member);

    Livewire::test('pages::invoices.index')
        ->set('status', 'paid')->assertSee('ACME/2026/001')->assertDontSee('ACME/2026/002')
        ->set('status', '')->set('search', 'Hooli')->assertSee('ACME/2026/002')->assertDontSee('ACME/2026/001');
});

test('the operator, who has no company, cannot open the invoice list', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('invoices.index'))->assertForbidden();
});

test('an invoice is created with items, a number and a total', function () {
    $this->actingAs($this->accountant);

    Livewire::test('pages::invoices.form')
        ->set('client_id', (string) $this->client->id)
        ->set('items', [
            ['description' => 'Consulting', 'quantity' => '3', 'unit_price' => '250.50'],
            ['description' => 'Hosting', 'quantity' => '1', 'unit_price' => '99,99'],
        ])
        ->assertSet('total', 85_149)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $invoice = Invoice::where('company_id', $this->company->id)->firstOrFail();

    expect($invoice->number)->toBe('ACME/'.now()->year.'/001')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->total)->toBe(85_149)
        ->and($invoice->user_id)->toBe($this->accountant->id)
        ->and($invoice->items()->count())->toBe(2);
});

test('an invoice cannot bill a client of another company', function () {
    $this->actingAs($this->accountant);

    Livewire::test('pages::invoices.form')
        ->set('client_id', (string) $this->foreign->client_id)
        ->set('items', [['description' => 'X', 'quantity' => '1', 'unit_price' => '1']])
        ->call('save')
        ->assertHasErrors('client_id');
});

test('invalid amounts are rejected', function (string $price) {
    $this->actingAs($this->accountant);

    Livewire::test('pages::invoices.form')
        ->set('client_id', (string) $this->client->id)
        ->set('items', [['description' => 'X', 'quantity' => '1', 'unit_price' => $price]])
        ->call('save')
        ->assertHasErrors('items.0.unit_price');
})->with(['-5', '1.234', 'abc', '1e5']);

test('a draft is edited and its items replaced', function () {
    $invoice = Invoice::factory()->draft()->withItems(3)->for($this->company)->for($this->client)->create();
    $this->actingAs($this->accountant);

    Livewire::test('pages::invoices.form', ['invoice' => $invoice])
        ->assertCount('items', 3)
        ->set('items', [['description' => 'Only line', 'quantity' => '2', 'unit_price' => '10']])
        ->call('save')
        ->assertHasNoErrors();

    expect($invoice->fresh()->total)->toBe(2_000)
        ->and($invoice->items()->pluck('description')->all())->toBe(['Only line']);
});

test('sent invoices and invoices of another company cannot be edited', function () {
    $sent = Invoice::factory()->sent()->for($this->company)->for($this->client)->create();
    $this->actingAs($this->accountant);

    $this->get(route('invoices.edit', $sent))->assertForbidden();
    $this->get(route('invoices.edit', $this->foreign))->assertForbidden();
});

test('an invoice of another company cannot be opened or changed', function () {
    $this->actingAs($this->accountant);

    $this->get(route('invoices.show', $this->foreign))->assertForbidden();
    $this->get(route('invoices.pdf', $this->foreign))->assertForbidden();
    $this->post(route('invoices.send', $this->foreign))->assertForbidden();
});

test('owners and accountants change the status, members do not', function () {
    $invoice = Invoice::factory()->sent()->for($this->company)->for($this->client)->create();

    $this->actingAs($this->member);
    Livewire::test('pages::invoices.show', ['invoice' => $invoice])->call('markAsPaid')->assertForbidden();

    $this->actingAs($this->accountant);
    Livewire::test('pages::invoices.show', ['invoice' => $invoice])->call('markAsPaid');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('a member deletes their own draft but not a draft of someone else', function () {
    $own = Invoice::factory()->draft()->for($this->company)->for($this->client)->create(['user_id' => $this->member->id]);
    $other = Invoice::factory()->draft()->for($this->company)->for($this->client)->create(['user_id' => $this->accountant->id]);

    $this->actingAs($this->member);

    Livewire::test('pages::invoices.show', ['invoice' => $other])->call('delete')->assertForbidden();
    Livewire::test('pages::invoices.show', ['invoice' => $own])->call('delete')->assertRedirect(route('invoices.index'));

    expect(Invoice::find($own->id))->toBeNull()->and(Invoice::find($other->id))->not->toBeNull();
});

test('duplicating creates a new draft with the same items', function () {
    $invoice = Invoice::factory()->paid()->withItems(2)->for($this->company)->for($this->client)->create(['number' => 'ACME/2026/001']);
    $this->actingAs($this->member);

    Livewire::test('pages::invoices.show', ['invoice' => $invoice])->call('duplicate')->assertRedirect();

    $copy = Invoice::where('id', '!=', $invoice->id)->where('company_id', $this->company->id)->firstOrFail();

    expect($copy->status)->toBe(InvoiceStatus::Draft)
        ->and($copy->number)->not->toBe($invoice->number)
        ->and($copy->total)->toBe($invoice->total)
        ->and($copy->user_id)->toBe($this->member->id);
});
