<?php

use App\Models\Company;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('All payments')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $company = '';

    #[Url(except: '')]
    public string $matched = '';

    public function mount(): void
    {
        $this->authorize('operate');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        return Payment::query()
            ->with('company', 'invoice')
            ->when(ctype_digit($this->company), fn ($query) => $query->where('company_id', (int) $this->company))
            ->when($this->matched === 'yes', fn ($query) => $query->whereNotNull('invoice_id'))
            ->when($this->matched === 'no', fn ($query) => $query->whereNull('invoice_id'))
            ->latest('paid_at')
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
        <flux:heading size="xl" level="1">{{ __('All payments') }}</flux:heading>
        <flux:subheading>{{ __('Payments reported by the gateway for every company, read only') }}</flux:subheading>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:select wire:model.live="company">
            <flux:select.option value="">{{ __('All companies') }}</flux:select.option>
            @foreach ($this->companies as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="matched">
            <flux:select.option value="">{{ __('Matched and unmatched') }}</flux:select.option>
            <flux:select.option value="yes">{{ __('Matched to an invoice') }}</flux:select.option>
            <flux:select.option value="no">{{ __('Unmatched') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->payments">
        <flux:table.columns>
            <flux:table.column>{{ __('Paid') }}</flux:table.column>
            <flux:table.column>{{ __('Company') }}</flux:table.column>
            <flux:table.column>{{ __('Reference') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
            <flux:table.column>{{ __('Invoice') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->payments as $payment)
                <flux:table.row :key="$payment->id">
                    <flux:table.cell>{{ $payment->paid_at->toDateString() }}</flux:table.cell>
                    <flux:table.cell><flux:link :href="route('admin.company', $payment->company)" wire:navigate>{{ $payment->company->name }}</flux:link></flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $payment->provider_reference }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($payment->amount, $payment->currency) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($payment->invoice)
                            <flux:link :href="route('invoices.show', $payment->invoice)" wire:navigate>{{ $payment->invoice->number }}</flux:link>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('Unmatched') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
