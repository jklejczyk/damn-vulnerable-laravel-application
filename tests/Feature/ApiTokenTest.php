<?php

use App\Models\User;

test('a company owner can create an API token that never expires (DVLA-014)', function () {
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->postJson(route('api-tokens.store'), ['name' => 'cli']);

    $response->assertCreated()->assertJsonStructure(['token']);
    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->expires_at)->toBeNull();
});

test('guests cannot create API tokens', function () {
    $this->postJson(route('api-tokens.store'), ['name' => 'cli'])->assertUnauthorized();
});

test('members, accountants and the operator cannot create API tokens', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->postJson(route('api-tokens.store'), ['name' => 'cli'])
        ->assertForbidden();
})->with(['member', 'accountant', 'admin']);
