<?php

use App\Jobs\SendInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->company = Company::factory()->create();
    $this->invoice = Invoice::factory()->sent()->withItems()->for($this->company)
        ->for(Client::factory()->for($this->company)->create(['email' => 'client@example.com']))
        ->create();
});

test('DVLA-SAFE-007: a guest is sent to the login page and nothing is queued', function () {
    $this->post(route('invoices.remind', $this->invoice))->assertRedirect(route('login'));

    Queue::assertNothingPushed();
});

test('DVLA-SAFE-007: a user of another company is refused', function () {
    $this->actingAs(User::factory()->owner()->for(Company::factory())->create())
        ->post(route('invoices.remind', $this->invoice))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('an owner queues a reminder for a sent invoice', function () {
    $this->actingAs(User::factory()->owner()->for($this->company)->create())
        ->post(route('invoices.remind', $this->invoice))
        ->assertRedirect(route('invoices.show', $this->invoice));

    Queue::assertPushed(SendInvoice::class);
});
