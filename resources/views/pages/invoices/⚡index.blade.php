<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Invoices')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $client = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
        abort_if(Auth::user()->currentCompany() === null, 403, 'Choose a company in the operator panel first.');
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'client', 'from', 'to');
        $this->resetPage();
    }

    /**
     * Only invoices of the signed-in user's company; filters narrow that set further.
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        return Invoice::query()
            ->whereBelongsTo(Auth::user()->currentCompany())
            ->with('client')
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', '%'.$this->search.'%'))))
            ->when(InvoiceStatus::tryFrom($this->status), fn (Builder $query, InvoiceStatus $status) => $query->where('status', $status))
            ->when(ctype_digit($this->client), fn (Builder $query) => $query->where('client_id', (int) $this->client))
            ->when($this->validDate($this->from), fn (Builder $query) => $query->whereDate('issue_date', '>=', $this->from))
            ->when($this->validDate($this->to), fn (Builder $query) => $query->whereDate('issue_date', '<=', $this->to))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function clients(): Collection
    {
        return Auth::user()->currentCompany()->clients()->orderBy('name')->get(['id', 'name']);
    }

    private function validDate(string $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Invoices') }}</flux:heading>
            <flux:subheading>{{ __('Invoices issued by :company', ['company' => auth()->user()->currentCompany()->name]) }}</flux:subheading>
        </div>

        @can('create', App\Models\Invoice::class)
            <flux:button variant="primary" icon="plus" :href="route('invoices.create')" wire:navigate>{{ __('New invoice') }}</flux:button>
        @endcan
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Number or client')" class="lg:col-span-2" />

        <flux:select wire:model.live="status" :placeholder="__('All statuses')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (App\Enums\InvoiceStatus::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="client" :placeholder="__('All clients')">
            <flux:select.option value="">{{ __('All clients') }}</flux:select.option>
            @foreach ($this->clients as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center gap-2">
            <flux:input type="date" wire:model.live="from" :aria-label="__('Issued from')" />
            <flux:input type="date" wire:model.live="to" :aria-label="__('Issued to')" />
        </div>
    </div>

    @if ($this->invoices->isEmpty())
        <flux:card class="flex flex-col items-center gap-3 py-12 text-center">
            <flux:icon.document-text class="size-10 text-zinc-400" />
            <flux:heading>{{ __('No invoices found') }}</flux:heading>
            @if ($search !== '' || $status !== '' || $client !== '' || $from !== '' || $to !== '')
                <flux:button size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
            @endif
        </flux:card>
    @else
        <flux:table :paginate="$this->invoices">
            <flux:table.columns>
                <flux:table.column>{{ __('Number') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Issued') }}</flux:table.column>
                <flux:table.column>{{ __('Due') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell>
                            <flux:link :href="route('invoices.show', $invoice)" wire:navigate>{{ $invoice->number }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->client->name }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->issue_date->toDateString() }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->due_date->toDateString() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$invoice->isOverdue() ? 'amber' : $invoice->status->color()">
                                {{ $invoice->isOverdue() ? __('Overdue') : $invoice->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($invoice->total, $invoice->currency) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
