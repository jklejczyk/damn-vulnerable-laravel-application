<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->accountant = User::factory()->accountant()->for($this->company)->create();
    $this->member = User::factory()->member()->for($this->company)->create();
    $this->foreign = Client::factory()->create(['name' => 'Foreign Client']);
});

test('the list shows and searches only clients of the company', function () {
    Client::factory()->for($this->company)->create(['name' => 'Wayne Enterprises']);
    Client::factory()->for($this->company)->create(['name' => 'Stark Industries']);

    $this->actingAs($this->member);

    Livewire::test('pages::clients.index')
        ->assertSee('Wayne Enterprises')->assertDontSee('Foreign Client')
        ->set('search', 'Stark')->assertSee('Stark Industries')->assertDontSee('Wayne Enterprises');
});

test('an accountant creates a client in their own company, whatever the request says', function () {
    $this->actingAs($this->accountant);

    Livewire::test('pages::clients.form')
        ->set('name', 'Hooli')
        ->set('email', 'billing@hooli.test')
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::where('name', 'Hooli')->firstOrFail()->company_id)->toBe($this->company->id);
});

test('members cannot create or edit clients', function () {
    $client = Client::factory()->for($this->company)->create();
    $this->actingAs($this->member);

    $this->get(route('clients.create'))->assertForbidden();
    $this->get(route('clients.edit', $client))->assertForbidden();
});

test('a client of another company cannot be opened or edited', function () {
    $this->actingAs($this->accountant);

    $this->get(route('clients.show', $this->foreign))->assertForbidden();
    $this->get(route('clients.edit', $this->foreign))->assertForbidden();
});

test('a client is edited and a client without invoices deleted', function () {
    $client = Client::factory()->for($this->company)->create();
    $this->actingAs($this->accountant);

    Livewire::test('pages::clients.form', ['client' => $client])->set('name', 'Renamed')->call('save')->assertHasNoErrors();
    expect($client->fresh()->name)->toBe('Renamed');

    Livewire::test('pages::clients.show', ['client' => $client])->call('delete')->assertRedirect(route('clients.index'));
    expect(Client::find($client->id))->toBeNull();
});

test('a client with invoices cannot be deleted', function () {
    $client = Client::factory()->for($this->company)->create();
    Invoice::factory()->for($this->company)->for($client)->create();
    $this->actingAs($this->accountant);

    Livewire::test('pages::clients.show', ['client' => $client])->call('delete')->assertForbidden();
});
