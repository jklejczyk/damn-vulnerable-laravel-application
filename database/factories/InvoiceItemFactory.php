<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->randomElement(['Consulting', 'Web development', 'Hosting', 'Design', 'Support plan', 'Training']),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->numberBetween(5_000, 200_000),
        ];
    }
}
