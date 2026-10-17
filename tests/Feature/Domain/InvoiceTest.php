<?php

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;

test('recalculate stores the sum of the items in grosze', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 3, 'unit_price' => 1_999]);
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 1, 'unit_price' => 1]);

    $invoice->recalculate();

    expect($invoice->fresh()->total)->toBe(5_998);
});

test('the next invoice number continues the yearly sequence of the company', function () {
    $company = Company::factory()->create(['slug' => 'acme']);
    Invoice::factory()->for($company)->create(['number' => 'ACME/2026/009']);
    Invoice::factory()->create(['number' => 'ACME/2026/050']);

    expect($company->nextInvoiceNumber(2026))->toBe('ACME/2026/010')
        ->and($company->nextInvoiceNumber(2027))->toBe('ACME/2027/001');
});

test('marking an invoice as sent records when', function () {
    $invoice = Invoice::factory()->draft()->create();

    $invoice->markAs(InvoiceStatus::Sent);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->fresh()->sent_at)->not->toBeNull();
});

test('a sent invoice past its due date is overdue', function () {
    expect(Invoice::factory()->sent()->create(['due_date' => now()->subDay()])->isOverdue())->toBeTrue()
        ->and(Invoice::factory()->paid()->create(['due_date' => now()->subDay()])->isOverdue())->toBeFalse();
});
