<?php

use App\Enums\InvoiceStatus;
use App\Models\Company;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Company')] class extends Component {
    public Company $company;

    public function mount(Company $company): void
    {
        $this->authorize('operate');

        $this->company = $company->load(['users', 'invoices' => fn ($query) => $query->with('client')->latest('issue_date')->limit(10)])->loadCount(['clients', 'invoices', 'payments']);
    }

    public function statusCounts(): array
    {
        return $this->company->invoices()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();
    }
}; ?>

<section class="w-full max-w-4xl space-y-6">
    <div>
        <flux:link :href="route('admin.companies')" wire:navigate>← {{ __('Companies') }}</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">{{ $company->name }}</flux:heading>
        <flux:subheading>{{ $company->slug }} · {{ __('Tax ID') }} {{ $company->tax_id }}</flux:subheading>
    </div>

    <form method="POST" action="{{ route('admin.operate', $company) }}">
        @csrf
        <flux:button type="submit" variant="primary" icon="arrow-right-end-on-rectangle">{{ __('Manage as operator') }}</flux:button>
        <flux:text class="mt-2 text-sm">{{ __('Opens every page of the app for this company, with full rights: invoices, clients, payments, team and settings.') }}</flux:text>
    </form>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card><flux:subheading>{{ __('Clients') }}</flux:subheading><flux:heading size="xl">{{ $company->clients_count }}</flux:heading></flux:card>
        <flux:card><flux:subheading>{{ __('Invoices') }}</flux:subheading><flux:heading size="xl">{{ $company->invoices_count }}</flux:heading></flux:card>
        <flux:card><flux:subheading>{{ __('Payments') }}</flux:subheading><flux:heading size="xl">{{ $company->payments_count }}</flux:heading></flux:card>
    </div>

    <flux:card class="flex flex-wrap gap-2">
        @foreach (App\Enums\InvoiceStatus::cases() as $status)
            <flux:badge :color="$status->color()">{{ $status->label() }}: {{ $this->statusCounts()[$status->value] ?? 0 }}</flux:badge>
        @endforeach
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('User') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($company->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>{{ $user->role->label() }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="flex items-center justify-between">
        <flux:heading>{{ __('Latest invoices') }}</flux:heading>
        <flux:link :href="route('admin.invoices', ['company' => $company->id])" wire:navigate>{{ __('All invoices of :company', ['company' => $company->name]) }}</flux:link>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Number') }}</flux:table.column>
            <flux:table.column>{{ __('Client') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($company->invoices as $invoice)
                <flux:table.row :key="$invoice->id">
                    <flux:table.cell><flux:link :href="route('invoices.show', $invoice)" wire:navigate>{{ $invoice->number }}</flux:link></flux:table.cell>
                    <flux:table.cell>{{ $invoice->client->name }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$invoice->status->color()">{{ $invoice->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($invoice->total, $invoice->currency) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
