<?php

use App\Enums\InvoiceStatus;
use App\Models\Payment;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Payments')] class extends Component {
    use WithPagination;

    /** @var array<int, string> payment id => invoice id chosen in the row */
    public array $assign = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Payment::class);
        abort_if(Auth::user()->currentCompany() === null, 403, 'Choose a company in the operator panel first.');
    }

    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        return Auth::user()->currentCompany()->payments()->with('invoice')->latest('paid_at')->paginate(20);
    }

    /**
     * Sent invoices of the company, the only ones an unmatched payment can settle.
     */
    #[Computed]
    public function openInvoices(): Collection
    {
        return Auth::user()->currentCompany()->invoices()->where('status', InvoiceStatus::Sent)->orderBy('number')->get(['id', 'number', 'total', 'currency']);
    }

    public function assignPayment(int $paymentId): void
    {
        $company = Auth::user()->currentCompany();
        $payment = $company->payments()->whereNull('invoice_id')->findOrFail($paymentId);

        $this->authorize('assign', $payment);

        $this->validate([
            "assign.{$paymentId}" => ['required', Rule::exists('invoices', 'id')->where('company_id', $company->id)->where('status', InvoiceStatus::Sent->value)],
        ]);

        $invoice = $company->invoices()->findOrFail((int) $this->assign[$paymentId]);

        $payment->invoice_id = $invoice->id;
        $payment->save();

        if ($invoice->payments()->sum('amount') >= $invoice->total) {
            $invoice->markAs(InvoiceStatus::Paid);
        }

        unset($this->assign[$paymentId]);
        Flux::toast(variant: 'success', text: __('Payment assigned to :number.', ['number' => $invoice->number]));
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Payments') }}</flux:heading>
        <flux:subheading>{{ __('Payments reported by the payment gateway') }}</flux:subheading>
    </div>

    @if ($this->payments->isEmpty())
        <flux:card class="py-12 text-center"><flux:heading>{{ __('No payments yet') }}</flux:heading></flux:card>
    @else
        <flux:table :paginate="$this->payments">
            <flux:table.columns>
                <flux:table.column>{{ __('Paid') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Invoice') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->payments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell>{{ $payment->paid_at->toDateString() }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $payment->provider_reference }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ App\Support\Money::format($payment->amount, $payment->currency) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($payment->invoice)
                                <flux:link :href="route('invoices.show', $payment->invoice)" wire:navigate>{{ $payment->invoice->number }}</flux:link>
                            @else
                                <div class="flex items-center gap-2">
                                    <flux:select size="sm" wire:model="assign.{{ $payment->id }}" :placeholder="__('Choose an invoice')">
                                        @foreach ($this->openInvoices as $invoice)
                                            <flux:select.option :value="$invoice->id">{{ $invoice->number }} · {{ App\Support\Money::format($invoice->total, $invoice->currency) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:button size="sm" wire:click="assignPayment({{ $payment->id }})">{{ __('Assign') }}</flux:button>
                                </div>
                                @error("assign.{$payment->id}") <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
