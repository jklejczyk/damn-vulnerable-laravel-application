<?php

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.payments.webhook_secret' => 'test-secret']);

    $this->company = Company::factory()->create(['slug' => 'acme']);
    $this->invoice = Invoice::factory()->sent()->for($this->company)->create(['number' => 'ACME/2026/002', 'total' => 200_000]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function paymentEvent(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'pay_123',
        'amount' => 200_000,
        'currency' => 'PLN',
        'paid_at' => '2026-10-17T10:00:00Z',
        'metadata' => ['company' => 'acme', 'invoice' => 'ACME/2026/002'],
    ], $overrides);
}

function postPaymentEvent(array $event, ?string $secret = 'test-secret'): TestResponse
{
    $body = json_encode($event);

    return test()->call('POST', route('webhooks.payments'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $secret === null ? '' : hash_hmac('sha256', $body, $secret),
    ], $body);
}

test('a signed event records the payment and settles the invoice', function () {
    postPaymentEvent(paymentEvent())->assertCreated();

    $payment = Payment::where('provider_reference', 'pay_123')->firstOrFail();

    expect($payment->company_id)->toBe($this->company->id)
        ->and($payment->invoice_id)->toBe($this->invoice->id)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('an event with a wrong or missing signature is rejected', function (?string $secret) {
    postPaymentEvent(paymentEvent(), $secret)->assertUnauthorized();

    expect(Payment::count())->toBe(0);
})->with(['wrong-secret', null]);

test('nothing is accepted when no secret is configured', function () {
    config(['services.payments.webhook_secret' => null]);

    postPaymentEvent(paymentEvent(), '')->assertUnauthorized();
});

test('a repeated event does not create a second payment', function () {
    postPaymentEvent(paymentEvent())->assertCreated();
    postPaymentEvent(paymentEvent())->assertOk()->assertJson(['status' => 'duplicate']);

    expect(Payment::count())->toBe(1);
});

test('a partial payment leaves the invoice open', function () {
    postPaymentEvent(paymentEvent(['amount' => 50_000]))->assertCreated();

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('an invoice number of another company is not matched', function () {
    Company::factory()->create(['slug' => 'globex']);

    postPaymentEvent(paymentEvent(['metadata' => ['company' => 'globex']]))->assertCreated();

    expect(Payment::firstOrFail()->invoice_id)->toBeNull()
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('the webhook needs no login and is excluded from CSRF (DVLA-SAFE-001, DVLA-SAFE-002)', function () {
    $route = app('router')->getRoutes()->getByName('webhooks.payments');
    $excluded = (fn () => static::$neverVerify)->bindTo(null, PreventRequestForgery::class)();

    expect($route->gatherMiddleware())->not->toContain('auth')
        ->and($excluded)->toContain('webhooks/payments');
});
