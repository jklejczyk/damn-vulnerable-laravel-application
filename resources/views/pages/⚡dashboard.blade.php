<?php

use App\Enums\InvoiceStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public function mount(): void
    {
        // The operator has no company of their own; until they pick one, their home is the operator panel.
        if (Auth::user()->currentCompany() === null) {
            $this->redirectRoute(Auth::user()->can('operate') ? 'admin.companies' : 'profile.edit', navigate: true);
        }
    }

    /**
     * Totals per currency of the signed-in user's company.
     *
     * @return array{outstanding: Collection, paidThisMonth: Collection, overdue: int}
     */
    #[Computed]
    public function stats(): array
    {
        $invoices = Auth::user()->currentCompany()->invoices();

        return [
            'outstanding' => (clone $invoices)->where('status', InvoiceStatus::Sent)->selectRaw('currency, sum(total) as amount')->groupBy('currency')->pluck('amount', 'currency'),
            'paidThisMonth' => Auth::user()->currentCompany()->payments()->where('paid_at', '>=', now()->startOfMonth())->selectRaw('currency, sum(amount) as amount')->groupBy('currency')->pluck('amount', 'currency'),
            'overdue' => (clone $invoices)->where('status', InvoiceStatus::Sent)->whereDate('due_date', '<', today())->count(),
        ];
    }

    #[Computed]
    public function recent(): Collection
    {
        return Auth::user()->currentCompany()->invoices()->with('client')->latest('issue_date')->latest('id')->limit(5)->get();
    }
}; ?>

<section class="w-full space-y-6">
    @if (auth()->user()->currentCompany())
        <div>
            <flux:heading size="xl" level="1">{{ auth()->user()->currentCompany()->name }}</flux:heading>
            <flux:subheading>{{ __('Signed in as :role', ['role' => auth()->user()->is_admin ? __('Operator') : auth()->user()->role->label()]) }}</flux:subheading>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:card>
                <flux:subheading>{{ __('Outstanding') }}</flux:subheading>
                @forelse ($this->stats['outstanding'] as $currency => $amount)
                    <flux:heading size="xl" class="tabular-nums">{{ App\Support\Money::format((int) $amount, $currency) }}</flux:heading>
                @empty
                    <flux:heading size="xl">–</flux:heading>
                @endforelse
            </flux:card>
            <flux:card>
                <flux:subheading>{{ __('Paid this month') }}</flux:subheading>
                @forelse ($this->stats['paidThisMonth'] as $currency => $amount)
                    <flux:heading size="xl" class="tabular-nums">{{ App\Support\Money::format((int) $amount, $currency) }}</flux:heading>
                @empty
                    <flux:heading size="xl">–</flux:heading>
                @endforelse
            </flux:card>
            <flux:card>
                <flux:subheading>{{ __('Overdue invoices') }}</flux:subheading>
                <flux:heading size="xl" class="tabular-nums {{ $this->stats['overdue'] > 0 ? 'text-amber-600' : '' }}">{{ $this->stats['overdue'] }}</flux:heading>
            </flux:card>
        </div>

        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Recent invoices') }}</flux:heading>
            <flux:link :href="route('invoices.index')" wire:navigate>{{ __('All invoices') }}</flux:link>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Number') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->recent as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell><flux:link :href="route('invoices.show', $invoice)" wire:navigate>{{ $invoice->number }}</flux:link></flux:table.cell>
                        <flux:table.cell>{{ $invoice->client->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$invoice->isOverdue() ? 'amber' : $invoice->status->color()">{{ $invoice->isOverdue() ? __('Overdue') : $invoice->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($invoice->total, $invoice->currency) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
