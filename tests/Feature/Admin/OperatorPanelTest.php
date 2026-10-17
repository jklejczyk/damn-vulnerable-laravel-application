<?php

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

test('the operator lists companies and opens one', function () {
    $company = Company::factory()->create(['name' => 'Acme Sp. z o.o.']);
    User::factory()->owner()->for($company)->create(['email' => 'owner@acme.test']);
    Invoice::factory()->sent()->for($company)->create();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.companies')->assertSee('Acme Sp. z o.o.');
    $this->get(route('admin.company', $company))->assertOk()->assertSee('owner@acme.test');
});

test('company users cannot open the operator panel', function () {
    $company = Company::factory()->create();

    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('admin.companies'))->assertForbidden();
    $this->get(route('admin.company', $company))->assertForbidden();
});

test('the operator is sent from the dashboard to the operator panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::dashboard')->assertRedirect(route('admin.companies'));
});

test('the operator lists users, invoices, clients and payments of every company', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@dvla.test')->firstOrFail());

    $this->get(route('admin.users'))->assertOk()->assertSee('owner@acme.test')->assertSee('member@globex.test');
    $this->get(route('admin.invoices'))->assertOk()->assertSee('ACME/'.now()->year.'/001')->assertSee('GLOBEX/'.now()->year.'/005');
    $this->get(route('admin.clients'))->assertOk()->assertSee('Wayne Enterprises')->assertSee('Hooli');
    $this->get(route('admin.payments'))->assertOk()->assertSee('pay_acme_1')->assertSee('pay_globex_1');

    Livewire::test('pages::admin.users')->set('company', (string) Company::where('slug', 'globex')->value('id'))
        ->assertSee('owner@globex.test')->assertDontSee('owner@acme.test');
});

test('the operator manages a company with every page of the app and leaves it', function () {
    $this->seed(DatabaseSeeder::class);
    $operator = User::where('email', 'admin@dvla.test')->firstOrFail();
    $acme = Company::where('slug', 'acme')->firstOrFail();

    $this->actingAs($operator);

    $this->get(route('invoices.index'))->assertForbidden();

    $this->post(route('admin.operate', $acme))->assertRedirect(route('dashboard'));

    foreach (['dashboard', 'invoices.index', 'invoices.create', 'clients.index', 'payments.index', 'team.index', 'company.settings'] as $name) {
        $this->get(route($name))->assertOk();
    }
    $this->get(route('dashboard'))->assertSee('Managing Acme Sp. z o.o.');
    $this->get(route('api-tokens.index'))->assertForbidden();

    $this->delete(route('admin.operate.stop'))->assertRedirect(route('admin.companies'));
    $this->get(route('invoices.index'))->assertForbidden();
});

test('the operator edits an invoice of any company, also while managing another one', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@dvla.test')->firstOrFail());
    $this->post(route('admin.operate', Company::where('slug', 'acme')->firstOrFail()));

    $draft = Invoice::where('number', 'GLOBEX/'.now()->year.'/004')->firstOrFail();

    Livewire::test('pages::invoices.form', ['invoice' => $draft])
        ->set('items', [['description' => 'Fixed by the operator', 'quantity' => '1', 'unit_price' => '10']])
        ->call('save')
        ->assertHasNoErrors();

    expect($draft->fresh()->total)->toBe(1_000)
        ->and($draft->fresh()->company->slug)->toBe('globex');

    $sent = Invoice::where('number', 'GLOBEX/'.now()->year.'/002')->firstOrFail();
    Livewire::test('pages::invoices.show', ['invoice' => $sent])->call('markAsPaid');

    expect($sent->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('the operator adds a member to a managed company', function () {
    $this->seed(DatabaseSeeder::class);
    $acme = Company::where('slug', 'acme')->firstOrFail();
    $this->actingAs(User::where('email', 'admin@dvla.test')->firstOrFail());
    $this->post(route('admin.operate', $acme));

    Livewire::test('pages::team.index')->set('name', 'New Hire')->set('email', 'new@acme.test')->set('role', 'accountant')->call('addMember')->assertHasNoErrors();

    expect(User::where('email', 'new@acme.test')->firstOrFail()->company_id)->toBe($acme->id);
});

test('company users cannot switch to another company', function () {
    $this->actingAs(User::factory()->owner()->create());

    $this->post(route('admin.operate', Company::factory()->create()))->assertForbidden();
});
