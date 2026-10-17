<?php

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('the seeder creates the documented accounts with the password "password"', function (string $email, ?string $company, string $role, bool $admin) {
    $user = User::where('email', $email)->firstOrFail();

    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->company?->slug)->toBe($company)
        ->and($user->role->value)->toBe($role)
        ->and($user->is_admin)->toBe($admin)
        ->and($user->hasVerifiedEmail())->toBeTrue();
})->with([
    ['admin@dvla.test', null, 'member', true],
    ['owner@acme.test', 'acme', 'owner', false],
    ['accountant@acme.test', 'acme', 'accountant', false],
    ['member@acme.test', 'acme', 'member', false],
    ['owner@globex.test', 'globex', 'owner', false],
    ['accountant@globex.test', 'globex', 'accountant', false],
    ['member@globex.test', 'globex', 'member', false],
]);

test('each company has its own clients and numbered invoices', function (string $slug) {
    $company = Company::where('slug', $slug)->firstOrFail();
    $year = now()->format('Y');

    expect($company->clients()->count())->toBe(3)
        ->and($company->invoices()->orderBy('number')->pluck('number')->all())
        ->toBe(array_map(fn (int $n) => sprintf('%s/%s/%03d', strtoupper($slug), $year, $n), range(1, 5)))
        ->and($company->invoices()->whereHas('client', fn ($q) => $q->where('company_id', '!=', $company->id))->exists())->toBeFalse();
})->with(['acme', 'globex']);

test('invoice totals match their items and the paid invoice has its payment', function () {
    $paid = Invoice::where('number', 'like', 'ACME/%/001')->firstOrFail();

    expect($paid->status)->toBe(InvoiceStatus::Paid)
        ->and($paid->total)->toBe(720_000)
        ->and($paid->payments()->sum('amount'))->toBe(720_000);
});
