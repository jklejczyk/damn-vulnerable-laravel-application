<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Invoice')] class extends Component {
    /** Null while creating; the model id cannot be changed from the browser. */
    #[Locked]
    public ?int $invoiceId = null;

    /** The company the invoice belongs to; for the operator it may differ from their current one. */
    #[Locked]
    public int $companyId;

    public string $client_id = '';

    public string $issue_date = '';

    public string $due_date = '';

    public string $currency = 'PLN';

    public string $notes = '';

    /** @var list<array{description: string, quantity: string, unit_price: string}> */
    public array $items = [];

    public function mount(?Invoice $invoice = null): void
    {
        if ($invoice?->exists) {
            $this->authorize('update', $invoice);

            $this->invoiceId = $invoice->id;
            $this->companyId = $invoice->company_id;
            $this->client_id = (string) $invoice->client_id;
            $this->issue_date = $invoice->issue_date->toDateString();
            $this->due_date = $invoice->due_date->toDateString();
            $this->currency = $invoice->currency;
            $this->notes = (string) $invoice->notes;
            $this->items = $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_price' => Money::toDecimal($item->unit_price),
            ])->all();

            return;
        }

        $this->authorize('create', Invoice::class);

        $company = Auth::user()->currentCompany() ?? abort(403, 'Choose a company in the operator panel first.');
        $this->companyId = $company->id;

        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays($company->payment_terms_days)->toDateString();
        $this->currency = $company->currency;
        $this->items = [$this->emptyItem()];
    }

    public function addItem(): void
    {
        $this->items[] = $this->emptyItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Sum of the valid lines, recomputed on every change; invalid lines count as zero.
     */
    #[Computed]
    public function total(): int
    {
        return array_sum(array_map(function (array $item): int {
            $quantity = ctype_digit((string) $item['quantity']) ? (int) $item['quantity'] : 0;
            $price = preg_match(Money::PATTERN, (string) $item['unit_price']) === 1 ? Money::toMinor((string) $item['unit_price']) : 0;

            return $quantity * $price;
        }, $this->items));
    }

    #[Computed]
    public function clients(): Collection
    {
        return Company::findOrFail($this->companyId)->clients()->orderBy('name')->get(['id', 'name']);
    }

    public function save(): void
    {
        $company = Company::findOrFail($this->companyId);
        $invoice = $this->invoiceId === null ? null : $company->invoices()->findOrFail($this->invoiceId);

        $invoice === null ? $this->authorize('create', Invoice::class) : $this->authorize('update', $invoice);

        $validated = $this->validate([
            // Only clients of the same company can be billed.
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $company->id)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', Rule::in(['PLN', 'EUR', 'USD'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'regex:'.Money::PATTERN],
        ]);

        $invoice = DB::transaction(function () use ($company, $invoice, $validated): Invoice {
            $invoice ??= tap(new Invoice, function (Invoice $new) use ($company) {
                $new->company_id = $company->id;
                $new->user_id = Auth::id();
                $new->number = $company->nextInvoiceNumber();
            });

            $invoice->fill([
                'client_id' => (int) $validated['client_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'currency' => $validated['currency'],
                'notes' => $validated['notes'] ?: null,
            ])->save();

            $invoice->items()->delete();

            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => Money::toMinor($item['unit_price']),
                ]);
            }

            $invoice->recalculate();

            return $invoice;
        });

        Flux::toast(variant: 'success', text: __('Invoice :number saved.', ['number' => $invoice->number]));

        $this->redirectRoute('invoices.show', $invoice, navigate: true);
    }

    /**
     * @return array{description: string, quantity: string, unit_price: string}
     */
    private function emptyItem(): array
    {
        return ['description' => '', 'quantity' => '1', 'unit_price' => ''];
    }
}; ?>

<section class="w-full max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ $invoiceId ? __('Edit invoice') : __('New invoice') }}</flux:heading>
        <flux:subheading>{{ __('Only drafts can be edited. Amounts are in :currency.', ['currency' => $currency]) }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="client_id" :label="__('Client')" :placeholder="__('Choose a client')" required>
                @foreach ($this->clients as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="currency" :label="__('Currency')">
                <flux:select.option value="PLN">PLN</flux:select.option>
                <flux:select.option value="EUR">EUR</flux:select.option>
                <flux:select.option value="USD">USD</flux:select.option>
            </flux:select>

            <flux:input type="date" wire:model="issue_date" :label="__('Issue date')" required />
            <flux:input type="date" wire:model="due_date" :label="__('Due date')" required />
        </div>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Items') }}</flux:heading>
                <flux:button size="sm" icon="plus" wire:click="addItem">{{ __('Add item') }}</flux:button>
            </div>

            @error('items') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror

            @foreach ($items as $index => $item)
                <div class="grid items-start gap-3 sm:grid-cols-12" wire:key="item-{{ $index }}">
                    <flux:input class="sm:col-span-6" wire:model="items.{{ $index }}.description" :placeholder="__('Description')" :aria-label="__('Description')" />
                    <flux:input class="sm:col-span-2" type="number" min="1" wire:model.live.debounce.300ms="items.{{ $index }}.quantity" :aria-label="__('Quantity')" />
                    <flux:input class="sm:col-span-3" inputmode="decimal" wire:model.live.debounce.300ms="items.{{ $index }}.unit_price" placeholder="0.00" :aria-label="__('Unit price')" />
                    <div class="sm:col-span-1 flex justify-end">
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeItem({{ $index }})" :disabled="count($items) === 1" :aria-label="__('Remove item')" />
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:heading size="lg" class="tabular-nums">{{ __('Total') }}: {{ App\Support\Money::format($this->total, $currency) }}</flux:heading>
            </div>
        </flux:card>

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button :href="$invoiceId ? route('invoices.show', $invoiceId) : route('invoices.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
