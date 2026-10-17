<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows figures of the user company only', function () {
    $company = Company::factory()->create();
    $user = User::factory()->member()->for($company)->create();
    Invoice::factory()->sent()->for($company)->create(['number' => 'ACME/2026/002', 'total' => 200_000, 'due_date' => now()->subDay()]);
    Invoice::factory()->sent()->create(['number' => 'GLOBEX/2026/002']);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::dashboard')
        ->assertSee('ACME/2026/002')
        ->assertDontSee('GLOBEX/2026/002')
        ->assertSee('2 000,00 PLN')
        ->assertSet('stats.overdue', 1);
});
