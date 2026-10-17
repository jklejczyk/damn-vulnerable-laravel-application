<?php

use App\Models\Client;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('All clients')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $company = '';

    public function mount(): void
    {
        $this->authorize('operate');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function clients(): LengthAwarePaginator
    {
        return Client::query()
            ->with('company')
            ->withCount('invoices')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when(ctype_digit($this->company), fn ($query) => $query->where('company_id', (int) $this->company))
            ->orderBy('name')
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
        <flux:heading size="xl" level="1">{{ __('All clients') }}</flux:heading>
        <flux:subheading>{{ __('Clients of every company, read only') }}</flux:subheading>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Name or email')" />
        <flux:select wire:model.live="company">
            <flux:select.option value="">{{ __('All companies') }}</flux:select.option>
            @foreach ($this->companies as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->clients">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Company') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Invoices') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->clients as $client)
                <flux:table.row :key="$client->id">
                    <flux:table.cell><flux:link :href="route('clients.show', $client)" wire:navigate>{{ $client->name }}</flux:link></flux:table.cell>
                    <flux:table.cell><flux:link :href="route('admin.company', $client->company)" wire:navigate>{{ $client->company->name }}</flux:link></flux:table.cell>
                    <flux:table.cell>{{ $client->email }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $client->invoices_count }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
