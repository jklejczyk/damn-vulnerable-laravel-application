<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $issued = fake()->dateTimeBetween('-60 days', 'now');

        return [
            'client_id' => Client::factory(),
            'company_id' => fn (array $attributes) => Client::query()->findOrFail($attributes['client_id'])->company_id,
            'number' => 'INV/'.fake()->unique()->numerify('######'),
            'status' => InvoiceStatus::Draft,
            'issue_date' => $issued,
            'due_date' => (clone $issued)->modify('+14 days'),
            'currency' => 'PLN',
            'total' => 0,
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => InvoiceStatus::Draft, 'sent_at' => null]);
    }

    public function sent(): static
    {
        return $this->state(['status' => InvoiceStatus::Sent, 'sent_at' => now()]);
    }

    public function paid(): static
    {
        return $this->state(['status' => InvoiceStatus::Paid, 'sent_at' => now()]);
    }

    public function void(): static
    {
        return $this->state(['status' => InvoiceStatus::Void]);
    }

    /**
     * Adds items and stores their sum as the total.
     */
    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($count) {
            InvoiceItem::factory()->count($count)->for($invoice)->create();
            $invoice->recalculate();
        });
    }
}
