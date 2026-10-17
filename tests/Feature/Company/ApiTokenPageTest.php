<?php

use App\Models\Company;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->owner()->for($this->company)->create();
});

test('an owner creates a token, sees it once and revokes it', function () {
    $this->actingAs($this->owner);

    $component = Livewire::test('pages::api-tokens.index')->set('name', 'Accounting')->call('createToken')->assertHasNoErrors();

    expect($component->get('plainTextToken'))->toBeString()
        ->and($this->owner->tokens()->count())->toBe(1);

    $component->call('revoke', $this->owner->tokens()->firstOrFail()->id);

    expect($this->owner->tokens()->count())->toBe(0);
});

test('a token of another user cannot be revoked', function () {
    $other = User::factory()->owner()->create();
    $token = $other->createToken('Other')->accessToken;

    $this->actingAs($this->owner);

    Livewire::test('pages::api-tokens.index')->call('revoke', $token->id)->assertNotFound();

    expect($other->tokens()->count())->toBe(1);
});

test('only owners manage API tokens', function () {
    $this->actingAs(User::factory()->accountant()->for($this->company)->create())
        ->get(route('api-tokens.index'))->assertForbidden();
});
