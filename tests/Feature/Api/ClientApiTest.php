<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('DVLA-SAFE-003: a company_id in the request is ignored when a client is created', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();

    Sanctum::actingAs(User::factory()->owner()->for($company)->create());

    $this->postJson(route('api.clients.store'), [
        'name' => 'Hooli',
        'company_id' => $other->id,
        'id' => 999,
    ])->assertCreated();

    $client = Client::where('name', 'Hooli')->firstOrFail();

    expect($client->company_id)->toBe($company->id)
        ->and($client->id)->not->toBe(999);
});

test('members cannot create clients through the API', function () {
    Sanctum::actingAs(User::factory()->member()->create());

    $this->postJson(route('api.clients.store'), ['name' => 'Hooli'])->assertForbidden();
});
