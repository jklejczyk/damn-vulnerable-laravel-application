<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Company settings')] class extends Component {
    use WithFileUploads;

    public string $name = '';

    public string $tax_id = '';

    public string $address = '';

    public string $currency = 'PLN';

    public string $payment_terms_days = '14';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $logo = null;

    public function mount(): void
    {
        abort_if(Auth::user()->currentCompany() === null, 403, 'Choose a company in the operator panel first.');
        $company = Auth::user()->currentCompany();
        $this->authorize('update', $company);

        $this->name = $company->name;
        $this->tax_id = (string) $company->tax_id;
        $this->address = (string) $company->address;
        $this->currency = $company->currency;
        $this->payment_terms_days = (string) $company->payment_terms_days;
    }

    public function save(): void
    {
        $company = Auth::user()->currentCompany();
        $this->authorize('update', $company);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', Rule::in(['PLN', 'EUR', 'USD'])],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            // Only PNG and JPEG, checked by content; the file name is generated, never taken from the upload.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'mimetypes:image/png,image/jpeg', 'max:1024'],
        ]);

        if ($this->logo !== null) {
            $path = $this->logo->store('logos/'.$company->id, 'public');

            if ($company->logo_path !== null) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $company->logo_path = $path;
        }

        $company->fill([
            'name' => $validated['name'],
            'tax_id' => $validated['tax_id'] ?: null,
            'address' => $validated['address'] ?: null,
            'currency' => $validated['currency'],
            'payment_terms_days' => (int) $validated['payment_terms_days'],
        ])->save();

        $this->reset('logo');
        Flux::toast(variant: 'success', text: __('Company settings saved.'));
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Company settings') }}</flux:heading>
        <flux:subheading>{{ __('Shown on every invoice') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Company name')" required />
        <flux:input wire:model="tax_id" :label="__('Tax ID')" />
        <flux:textarea wire:model="address" :label="__('Address')" rows="3" />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="currency" :label="__('Default currency')">
                <flux:select.option value="PLN">PLN</flux:select.option>
                <flux:select.option value="EUR">EUR</flux:select.option>
                <flux:select.option value="USD">USD</flux:select.option>
            </flux:select>
            <flux:input wire:model="payment_terms_days" type="number" min="0" max="180" :label="__('Payment terms (days)')" />
        </div>

        <div class="space-y-2">
            <flux:label>{{ __('Logo') }}</flux:label>
            @if (auth()->user()->currentCompany()->logo_path)
                <img src="{{ Storage::disk('public')->url(auth()->user()->currentCompany()->logo_path) }}" alt="" class="h-12">
            @endif
            <input type="file" wire:model="logo" accept="image/png,image/jpeg" class="block text-sm">
            <flux:error name="logo" />
            <flux:description>{{ __('PNG or JPEG, up to 1 MB.') }}</flux:description>
        </div>

        <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
    </form>
</section>
