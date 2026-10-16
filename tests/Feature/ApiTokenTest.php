<?php

use App\Models\User;

test('a verified user can create an API token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api-tokens.store'), ['name' => 'cli']);

    $response->assertCreated()->assertJsonStructure(['token']);
    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->expires_at)->toBeNull();
});

test('guests cannot create API tokens', function () {
    $this->postJson(route('api-tokens.store'), ['name' => 'cli'])->assertUnauthorized();
});
