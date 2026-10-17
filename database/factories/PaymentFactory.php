<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'invoice_id' => null,
            'amount' => fake()->numberBetween(10_000, 500_000),
            'currency' => 'PLN',
            'provider_reference' => 'pay_'.Str::random(16),
            'paid_at' => now(),
        ];
    }

    /**
     * A payment of the whole invoice.
     */
    public function forInvoice(Invoice $invoice): static
    {
        return $this->state([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
        ]);
    }
}
