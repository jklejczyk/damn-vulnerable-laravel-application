<?php

use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoice;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->client = Client::factory()->for($this->company)->create(['email' => 'billing@wayne.test']);
    $this->invoice = Invoice::factory()->draft()->withItems()->for($this->company)->for($this->client)->create(['number' => 'ACME/2026/001']);
});

test('the PDF is rendered with dompdf for members of the company', function () {
    $this->actingAs(User::factory()->member()->for($this->company)->create());

    $response = $this->get(route('invoices.pdf', $this->invoice))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('ACME-2026-001.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('sending queues the email and marks a draft as sent', function () {
    Queue::fake();
    $this->actingAs(User::factory()->accountant()->for($this->company)->create());

    $this->post(route('invoices.send', $this->invoice))->assertRedirect(route('invoices.show', $this->invoice));

    Queue::assertPushed(SendInvoice::class, fn (SendInvoice $job) => $job->invoice->is($this->invoice));
    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('the job emails the invoice with the PDF attached', function () {
    Mail::fake();

    (new SendInvoice($this->invoice))->handle();

    Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('billing@wayne.test')
        && $mail->attachments()[0]->as === 'ACME-2026-001.pdf');
});

test('members cannot send invoices', function () {
    Queue::fake();
    $this->actingAs(User::factory()->member()->for($this->company)->create());

    $this->post(route('invoices.send', $this->invoice))->assertForbidden();

    Queue::assertNothingPushed();
});
