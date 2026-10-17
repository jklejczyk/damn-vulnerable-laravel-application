<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

/**
 * A company with one user of each role, a client and invoices in each state.
 *
 * @return array{company: Company, owner: User, accountant: User, member: User, draft: Invoice, sent: Invoice, ownDraft: Invoice, client: Client}
 */
function companyWithTeam(): array
{
    $company = Company::factory()->create();
    $owner = User::factory()->owner()->for($company)->create();
    $accountant = User::factory()->accountant()->for($company)->create();
    $member = User::factory()->member()->for($company)->create();
    $client = Client::factory()->for($company)->create();

    return [
        'company' => $company,
        'owner' => $owner,
        'accountant' => $accountant,
        'member' => $member,
        'client' => $client,
        'draft' => Invoice::factory()->draft()->for($company)->for($client)->create(['user_id' => $accountant->id]),
        'sent' => Invoice::factory()->sent()->for($company)->for($client)->create(['user_id' => $accountant->id]),
        'ownDraft' => Invoice::factory()->draft()->for($company)->for($client)->create(['user_id' => $member->id]),
    ];
}

test('invoice permissions follow the role within the company', function (string $role, string $ability, string $invoice, bool $allowed) {
    $team = companyWithTeam();

    expect($team[$role]->can($ability, $team[$invoice]))->toBe($allowed);
})->with([
    ['owner', 'view', 'sent', true],
    ['owner', 'update', 'draft', true],
    ['owner', 'update', 'sent', false],
    ['owner', 'changeStatus', 'sent', true],
    ['accountant', 'update', 'draft', true],
    ['accountant', 'delete', 'draft', true],
    ['accountant', 'send', 'draft', true],
    ['member', 'view', 'sent', true],
    ['member', 'update', 'draft', false],
    ['member', 'update', 'ownDraft', true],
    ['member', 'delete', 'ownDraft', true],
    ['member', 'changeStatus', 'ownDraft', false],
    ['member', 'send', 'ownDraft', false],
    ['member', 'duplicate', 'sent', true],
]);

test('nobody outside the company can touch its invoices', function (string $ability) {
    $team = companyWithTeam();

    expect(User::factory()->owner()->create()->can($ability, $team['draft']))->toBeFalse();
})->with(['view', 'update', 'delete', 'changeStatus', 'send', 'duplicate']);

test('the operator may do anything in any company except managing API tokens', function () {
    $team = companyWithTeam();
    $operator = User::factory()->admin()->create();

    foreach (['view', 'update', 'delete', 'changeStatus', 'send', 'duplicate'] as $ability) {
        expect($operator->can($ability, $team['draft']))->toBeTrue();
    }

    expect($operator->can('update', $team['client']))->toBeTrue()
        ->and($operator->can('manageMembers', $team['company']))->toBeTrue()
        ->and($operator->can('update', $team['company']))->toBeTrue()
        ->and($operator->can('manageTokens', $team['company']))->toBeFalse();
});

test('clients are visible to the whole company and managed by owners and accountants', function () {
    $team = companyWithTeam();
    $outsider = User::factory()->owner()->create();

    expect($team['member']->can('view', $team['client']))->toBeTrue()
        ->and($team['member']->can('update', $team['client']))->toBeFalse()
        ->and($team['member']->can('create', Client::class))->toBeFalse()
        ->and($team['accountant']->can('update', $team['client']))->toBeTrue()
        ->and($outsider->can('view', $team['client']))->toBeFalse()
        ->and($outsider->can('update', $team['client']))->toBeFalse();
});

test('a client with invoices cannot be deleted', function () {
    $team = companyWithTeam();
    $empty = Client::factory()->for($team['company'])->create();

    expect($team['owner']->can('delete', $team['client']))->toBeFalse()
        ->and($team['owner']->can('delete', $empty))->toBeTrue();
});

test('payments are assigned by owners and accountants of the same company', function () {
    $team = companyWithTeam();
    $payment = Payment::factory()->for($team['company'])->create();
    $foreign = Payment::factory()->create();

    expect($team['accountant']->can('assign', $payment))->toBeTrue()
        ->and($team['member']->can('assign', $payment))->toBeFalse()
        ->and($team['accountant']->can('assign', $foreign))->toBeFalse()
        ->and($team['member']->can('viewAny', Payment::class))->toBeFalse();
});

test('company settings, members and tokens belong to its owners', function (string $ability) {
    $team = companyWithTeam();

    expect($team['owner']->can($ability, $team['company']))->toBeTrue()
        ->and($team['accountant']->can($ability, $team['company']))->toBeFalse()
        ->and(User::factory()->owner()->create()->can($ability, $team['company']))->toBeFalse();
})->with(['update', 'manageMembers', 'manageTokens']);

test('only the operator passes the operate gate', function () {
    expect(User::factory()->admin()->create()->can('operate'))->toBeTrue()
        ->and(User::factory()->owner()->create()->can('operate'))->toBeFalse();
});
