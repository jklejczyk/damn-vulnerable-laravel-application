<?php

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('All invoices')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $company = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('operate');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        return Invoice::query()
            ->with('company', 'client')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhereHas('client', fn ($client) => $client->where('name', 'like', '%'.$this->search.'%'))))
            ->when(ctype_digit($this->company), fn ($query) => $query->where('company_id', (int) $this->company))
            ->when(InvoiceStatus::tryFrom($this->status), fn ($query, InvoiceStatus $status) => $query->where('status', $status))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function companies(): Collection
    {
        return Company::orderBy('name')->get(['id', 'name']);
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('All invoices') }}</flux:heading>
        <flux:subheading>{{ __('Invoices of every company, read only') }}</flux:subheading>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Number or client')" />
        <flux:select wire:model.live="company">
            <flux:select.option value="">{{ __('All companies') }}</flux:select.option>
            @foreach ($this->companies as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (App\Enums\InvoiceStatus::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->invoices">
        <flux:table.columns>
            <flux:table.column>{{ __('Number') }}</flux:table.column>
            <flux:table.column>{{ __('Company') }}</flux:table.column>
            <flux:table.column>{{ __('Client') }}</flux:table.column>
            <flux:table.column>{{ __('Issued') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->invoices as $invoice)
                <flux:table.row :key="$invoice->id">
                    <flux:table.cell><flux:link :href="route('invoices.show', $invoice)" wire:navigate>{{ $invoice->number }}</flux:link></flux:table.cell>
                    <flux:table.cell><flux:link :href="route('admin.company', $invoice->company)" wire:navigate>{{ $invoice->company->name }}</flux:link></flux:table.cell>
                    <flux:table.cell>{{ $invoice->client->name }}</flux:table.cell>
                    <flux:table.cell>{{ $invoice->issue_date->toDateString() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$invoice->isOverdue() ? 'amber' : $invoice->status->color()">{{ $invoice->isOverdue() ? __('Overdue') : $invoice->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($invoice->total, $invoice->currency) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
