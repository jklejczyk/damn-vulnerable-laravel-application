<?php

use App\Models\Client;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Client')] class extends Component {
    public Client $client;

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);

        $this->client = $client->load(['invoices' => fn ($query) => $query->latest('issue_date')]);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->client);

        $this->client->delete();

        Flux::toast(text: __('Client deleted.'));
        $this->redirectRoute('clients.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-4xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $client->name }}</flux:heading>
            <flux:subheading>{{ $client->email }} @if ($client->tax_id) · {{ __('Tax ID') }} {{ $client->tax_id }} @endif</flux:subheading>
        </div>

        <div class="flex gap-2">
            @can('update', $client)
                <flux:button icon="pencil-square" :href="route('clients.edit', $client)" wire:navigate>{{ __('Edit') }}</flux:button>
            @endcan
            @can('delete', $client)
                <flux:button icon="trash" variant="danger" wire:click="delete" wire:confirm="{{ __('Delete this client?') }}">{{ __('Delete') }}</flux:button>
            @endcan
        </div>
    </div>

    @if ($client->address)
        <flux:text class="whitespace-pre-line">{{ $client->address }}</flux:text>
    @endif

    <flux:heading>{{ __('Invoices') }}</flux:heading>

    @if ($client->invoices->isEmpty())
        <flux:text>{{ __('No invoices yet.') }}</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Number') }}</flux:table.column>
                <flux:table.column>{{ __('Issued') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($client->invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell><flux:link :href="route('invoices.show', $invoice)" wire:navigate>{{ $invoice->number }}</flux:link></flux:table.cell>
                        <flux:table.cell>{{ $invoice->issue_date->toDateString() }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$invoice->status->color()">{{ $invoice->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($invoice->total, $invoice->currency) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
