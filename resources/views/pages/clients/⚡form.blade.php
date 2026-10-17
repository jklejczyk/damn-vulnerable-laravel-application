<?php

use App\Models\Client;
use App\Models\Company;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Client')] class extends Component {
    #[Locked]
    public ?int $clientId = null;

    #[Locked]
    public int $companyId;

    public string $name = '';

    public string $email = '';

    public string $tax_id = '';

    public string $address = '';

    public function mount(?Client $client = null): void
    {
        if ($client?->exists) {
            $this->authorize('update', $client);
            $this->clientId = $client->id;
            $this->companyId = $client->company_id;
            $this->fill($client->only('name', 'email', 'tax_id', 'address'));

            return;
        }

        $this->authorize('create', Client::class);

        $this->companyId = (Auth::user()->currentCompany() ?? abort(403, 'Choose a company in the operator panel first.'))->id;
    }

    public function save(): void
    {
        $company = Company::findOrFail($this->companyId);
        $client = $this->clientId === null ? null : $company->clients()->findOrFail($this->clientId);

        $client === null ? $this->authorize('create', Client::class) : $this->authorize('update', $client);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        // company_id is not fillable; a new client always belongs to the signed-in user's company.
        $client ??= $company->clients()->make();
        $client->fill($validated)->save();

        Flux::toast(variant: 'success', text: __('Client saved.'));
        $this->redirectRoute('clients.show', $client, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">{{ $clientId ? __('Edit client') : __('New client') }}</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Name')" required />
        <flux:input wire:model="email" type="email" :label="__('Billing email')" />
        <flux:input wire:model="tax_id" :label="__('Tax ID')" />
        <flux:textarea wire:model="address" :label="__('Address')" rows="3" />

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button :href="$clientId ? route('clients.show', $clientId) : route('clients.index')" wire:navigate variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>
