<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Invoice')] class extends Component {
    /** Livewire refuses changes to a model property from the browser. */
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);

        $this->invoice = $invoice->load('client', 'items', 'payments', 'user');
    }

    public function markAsPaid(): void
    {
        $this->authorize('changeStatus', $this->invoice);

        if ($this->invoice->status !== InvoiceStatus::Sent) {
            return;
        }

        $this->invoice->markAs(InvoiceStatus::Paid);
        Flux::toast(variant: 'success', text: __('Marked as paid.'));
    }

    public function void(): void
    {
        $this->authorize('changeStatus', $this->invoice);

        if (! in_array($this->invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Sent], true)) {
            return;
        }

        $this->invoice->markAs(InvoiceStatus::Void);
        Flux::toast(text: __('Invoice voided.'));
    }

    public function duplicate(): void
    {
        $this->authorize('duplicate', $this->invoice);

        $company = $this->invoice->company;

        $copy = DB::transaction(function () use ($company): Invoice {
            $copy = $this->invoice->replicate(['number', 'status', 'sent_at', 'total']);
            $copy->number = $company->nextInvoiceNumber();
            $copy->status = InvoiceStatus::Draft;
            $copy->user_id = Auth::id();
            $copy->issue_date = now();
            $copy->due_date = now()->addDays($company->payment_terms_days);
            $copy->save();

            foreach ($this->invoice->items as $item) {
                $copy->items()->create($item->only('description', 'quantity', 'unit_price'));
            }

            $copy->recalculate();

            return $copy;
        });

        Flux::toast(variant: 'success', text: __('Draft :number created.', ['number' => $copy->number]));
        $this->redirectRoute('invoices.edit', $copy, navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->invoice);

        $this->invoice->delete();

        Flux::toast(text: __('Draft deleted.'));
        $this->redirectRoute('invoices.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-4xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $invoice->number }}</flux:heading>
                <flux:badge :color="$invoice->isOverdue() ? 'amber' : $invoice->status->color()">
                    {{ $invoice->isOverdue() ? __('Overdue') : $invoice->status->label() }}
                </flux:badge>
            </div>
            <flux:subheading>{{ __('Issued :issued, due :due', ['issued' => $invoice->issue_date->toDateString(), 'due' => $invoice->due_date->toDateString()]) }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="arrow-down-tray" :href="route('invoices.pdf', $invoice)">{{ __('PDF') }}</flux:button>

            @can('update', $invoice)
                <flux:button icon="pencil-square" :href="route('invoices.edit', $invoice)" wire:navigate>{{ __('Edit') }}</flux:button>
            @endcan

            @can('send', $invoice)
                @if ($invoice->status !== App\Enums\InvoiceStatus::Void && $invoice->status !== App\Enums\InvoiceStatus::Paid)
                    <form method="POST" action="{{ route('invoices.send', $invoice) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" icon="paper-airplane">
                            {{ $invoice->sent_at ? __('Send again') : __('Send') }}
                        </flux:button>
                    </form>
                @endif
            @endcan

            <flux:dropdown>
                <flux:button icon="ellipsis-horizontal" :aria-label="__('More actions')" />
                <flux:menu>
                    @can('duplicate', $invoice)
                        <flux:menu.item icon="document-duplicate" wire:click="duplicate">{{ __('Duplicate') }}</flux:menu.item>
                    @endcan
                    @can('changeStatus', $invoice)
                        @if ($invoice->status === App\Enums\InvoiceStatus::Sent)
                            <flux:menu.item icon="check-circle" wire:click="markAsPaid">{{ __('Mark as paid') }}</flux:menu.item>
                        @endif
                        @if (in_array($invoice->status, [App\Enums\InvoiceStatus::Draft, App\Enums\InvoiceStatus::Sent], true))
                            <flux:menu.item icon="no-symbol" wire:click="void" wire:confirm="{{ __('Void this invoice?') }}">{{ __('Void') }}</flux:menu.item>
                        @endif
                    @endcan
                    @can('delete', $invoice)
                        <flux:menu.item icon="trash" variant="danger" wire:click="delete" wire:confirm="{{ __('Delete this draft?') }}">{{ __('Delete') }}</flux:menu.item>
                    @endcan
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card>
            <flux:subheading>{{ __('Billed to') }}</flux:subheading>
            <flux:heading>{{ $invoice->client->name }}</flux:heading>
            <flux:text class="whitespace-pre-line">{{ $invoice->client->address }}</flux:text>
            <flux:text>{{ $invoice->client->email }}</flux:text>
        </flux:card>
        <flux:card>
            <flux:subheading>{{ __('Issued by') }}</flux:subheading>
            <flux:heading>{{ $invoice->user?->name ?? __('Deleted user') }}</flux:heading>
            @if ($invoice->sent_at)
                <flux:text>{{ __('Sent :date', ['date' => $invoice->sent_at->toDateTimeString()]) }}</flux:text>
            @endif
        </flux:card>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Description') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Unit price') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($invoice->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>{{ $item->description }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $item->quantity }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($item->unit_price, $invoice->currency) }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($item->lineTotal(), $invoice->currency) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
            <flux:table.row>
                <flux:table.cell colspan="3" align="end" class="font-semibold">{{ __('Total') }}</flux:table.cell>
                <flux:table.cell align="end" class="font-semibold tabular-nums">{{ App\Support\Money::format($invoice->total, $invoice->currency) }}</flux:table.cell>
            </flux:table.row>
        </flux:table.rows>
    </flux:table>

    @if ($invoice->notes)
        <flux:card>
            <flux:subheading>{{ __('Notes') }}</flux:subheading>
            <flux:text class="whitespace-pre-line">{{ $invoice->notes }}</flux:text>
        </flux:card>
    @endif

    @if ($invoice->payments->isNotEmpty())
        <flux:card class="space-y-2">
            <flux:heading>{{ __('Payments') }}</flux:heading>
            @foreach ($invoice->payments as $payment)
                <flux:text>{{ $payment->paid_at->toDateString() }} · {{ App\Support\Money::format($payment->amount, $payment->currency) }} · {{ $payment->provider_reference }}</flux:text>
            @endforeach
        </flux:card>
    @endif
</section>
