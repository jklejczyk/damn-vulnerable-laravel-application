<?php

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Clients')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Client::class);
        abort_if(Auth::user()->currentCompany() === null, 403, 'Choose a company in the operator panel first.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function clients(): LengthAwarePaginator
    {
        return Auth::user()->currentCompany()->clients()
            ->withCount('invoices')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
                ->orWhere('tax_id', 'like', '%'.$this->search.'%')))
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Clients') }}</flux:heading>
            <flux:subheading>{{ __('Companies and people you invoice') }}</flux:subheading>
        </div>

        @can('create', App\Models\Client::class)
            <flux:button variant="primary" icon="plus" :href="route('clients.create')" wire:navigate>{{ __('New client') }}</flux:button>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Name, email or tax ID')" class="max-w-md" />

    @if ($this->clients->isEmpty())
        <flux:card class="py-12 text-center">
            <flux:heading>{{ __('No clients found') }}</flux:heading>
        </flux:card>
    @else
        <flux:table :paginate="$this->clients">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Tax ID') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Invoices') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->clients as $client)
                    <flux:table.row :key="$client->id">
                        <flux:table.cell><flux:link :href="route('clients.show', $client)" wire:navigate>{{ $client->name }}</flux:link></flux:table.cell>
                        <flux:table.cell>{{ $client->email }}</flux:table.cell>
                        <flux:table.cell>{{ $client->tax_id }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $client->invoices_count }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
