<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->owner = User::factory()->owner()->for($this->company)->create();
    $this->member = User::factory()->member()->for($this->company)->create(['name' => 'Mia Member']);
});

test('only owners open the team page', function () {
    $this->actingAs($this->member)->get(route('team.index'))->assertForbidden();
    $this->actingAs(User::factory()->accountant()->for($this->company)->create())->get(route('team.index'))->assertForbidden();
    $this->actingAs($this->owner)->get(route('team.index'))->assertOk()->assertSee('Mia Member');
});

test('an owner adds a member to their own company with a temporary password', function () {
    $this->actingAs($this->owner);

    $component = Livewire::test('pages::team.index')
        ->set('name', 'Ann Accountant')
        ->set('email', 'ann@acme.test')
        ->set('role', 'accountant')
        ->call('addMember')
        ->assertHasNoErrors();

    $user = User::where('email', 'ann@acme.test')->firstOrFail();

    expect($user->company_id)->toBe($this->company->id)
        ->and($user->role)->toBe(Role::Accountant)
        ->and($user->is_admin)->toBeFalse()
        ->and(Hash::check($component->get('temporaryPassword'), $user->password))->toBeTrue();
});

test('an owner changes roles but keeps at least one owner', function () {
    $this->actingAs($this->owner);

    Livewire::test('pages::team.index')->call('changeRole', $this->member->id, 'accountant');
    expect($this->member->fresh()->role)->toBe(Role::Accountant);

    Livewire::test('pages::team.index')->call('changeRole', $this->owner->id, 'member');
    expect($this->owner->fresh()->role)->toBe(Role::Owner);
});

test('members of another company cannot be changed or removed', function () {
    $foreign = User::factory()->member()->create();
    $this->actingAs($this->owner);

    Livewire::test('pages::team.index')->call('changeRole', $foreign->id, 'owner')->assertNotFound();
    Livewire::test('pages::team.index')->call('removeMember', $foreign->id)->assertNotFound();

    expect($foreign->fresh()->role)->toBe(Role::Member);
});

test('an owner removes a member but not themselves', function () {
    $this->actingAs($this->owner);

    Livewire::test('pages::team.index')->call('removeMember', $this->owner->id);
    Livewire::test('pages::team.index')->call('removeMember', $this->member->id);

    expect(User::find($this->owner->id))->not->toBeNull()
        ->and(User::find($this->member->id))->toBeNull();
});
