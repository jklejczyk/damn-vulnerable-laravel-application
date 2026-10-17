<?php

use App\Models\Company;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'company' => 'Initech',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->company->name)->toBe('Initech')
        ->and($user->company->slug)->toBe('initech')
        ->and($user->isOwner())->toBeTrue();
});

test('registration ignores a role or company sent with the form', function () {
    $other = Company::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'Mallory',
        'company' => 'Initech',
        'email' => 'mallory@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_id' => $other->id,
        'is_admin' => true,
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'mallory@example.com')->firstOrFail();
    expect($user->company_id)->not->toBe($other->id)
        ->and($user->is_admin)->toBeFalse();
});
