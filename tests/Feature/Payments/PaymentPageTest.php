<?php

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->accountant = User::factory()->accountant()->for($this->company)->create();
    $this->invoice = Invoice::factory()->sent()->for($this->company)->create(['total' => 10_000]);
    $this->payment = Payment::factory()->for($this->company)->create(['amount' => 10_000, 'provider_reference' => 'pay_own']);
});

test('the list shows only payments of the company', function () {
    Payment::factory()->create(['provider_reference' => 'pay_foreign']);
    $this->actingAs($this->accountant);

    Livewire::test('pages::payments.index')->assertSee('pay_own')->assertDontSee('pay_foreign');
});

test('members cannot see payments', function () {
    $this->actingAs(User::factory()->member()->for($this->company)->create())
        ->get(route('payments.index'))->assertForbidden();
});

test('an unmatched payment is assigned to a sent invoice and settles it', function () {
    $this->actingAs($this->accountant);

    Livewire::test('pages::payments.index')
        ->set("assign.{$this->payment->id}", (string) $this->invoice->id)
        ->call('assignPayment', $this->payment->id)
        ->assertHasNoErrors();

    expect($this->payment->fresh()->invoice_id)->toBe($this->invoice->id)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('a payment cannot be assigned to an invoice of another company', function () {
    $foreign = Invoice::factory()->sent()->create();
    $this->actingAs($this->accountant);

    Livewire::test('pages::payments.index')
        ->set("assign.{$this->payment->id}", (string) $foreign->id)
        ->call('assignPayment', $this->payment->id)
        ->assertHasErrors("assign.{$this->payment->id}");

    expect($this->payment->fresh()->invoice_id)->toBeNull();
});

test('a payment of another company cannot be assigned', function () {
    $foreign = Payment::factory()->create();
    $this->actingAs($this->accountant);

    Livewire::test('pages::payments.index')
        ->set("assign.{$foreign->id}", (string) $this->invoice->id)
        ->call('assignPayment', $foreign->id)
        ->assertNotFound();
});
