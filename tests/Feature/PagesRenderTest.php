<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('every page renders with the full layout for an owner', function () {
    $this->seed(DatabaseSeeder::class);
    $owner = User::where('email', 'owner@acme.test')->firstOrFail();
    $company = $owner->company;
    $invoice = $company->invoices()->where('status', 'draft')->firstOrFail();
    $client = $company->clients()->firstOrFail();

    $this->actingAs($owner);

    foreach ([
        route('home'), route('dashboard'),
        route('invoices.index'), route('invoices.create'), route('invoices.show', $invoice), route('invoices.edit', $invoice),
        route('clients.index'), route('clients.create'), route('clients.show', $client), route('clients.edit', $client),
        route('payments.index'), route('team.index'), route('company.settings'), route('api-tokens.index'),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

test('the operator panel renders for the operator', function () {
    $this->seed(DatabaseSeeder::class);

    $this->actingAs(User::where('email', 'admin@dvla.test')->firstOrFail());

    $this->get(route('admin.companies'))->assertOk()->assertSee('Globex S.A.');
    $this->get(route('admin.company', Company::where('slug', 'acme')->firstOrFail()))->assertOk();
});
