<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API tokens')] class extends Component {
    public string $name = '';

    /** Shown once after creating a token. */
    public ?string $plainTextToken = null;

    public function mount(): void
    {
        $this->authorize('manageTokens', Auth::user()->company);
    }

    #[Computed]
    public function tokens(): Collection
    {
        return Auth::user()->tokens()->latest()->get();
    }

    /**
     * DVLA-014: like ApiTokenController, the token gets no expiry date and sanctum.expiration is null.
     */
    public function createToken(): void
    {
        $this->authorize('manageTokens', Auth::user()->company);

        $this->validate(['name' => ['required', 'string', 'max:255']]);

        $this->plainTextToken = Auth::user()->createToken($this->name)->plainTextToken;

        $this->reset('name');
        unset($this->tokens);
    }

    public function revoke(int $tokenId): void
    {
        $this->authorize('manageTokens', Auth::user()->company);

        // Only the user's own tokens can be found here.
        Auth::user()->tokens()->whereKey($tokenId)->firstOrFail()->delete();

        unset($this->tokens);
        Flux::toast(text: __('Token revoked.'));
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('API tokens') }}</flux:heading>
        <flux:subheading>{{ __('Connect your accounting software to the DVLA API') }}</flux:subheading>
    </div>

    @if ($plainTextToken)
        <flux:callout variant="success" icon="key" :heading="__('Copy your token now; it will not be shown again.')">
            <flux:callout.text><code class="font-mono break-all">{{ $plainTextToken }}</code></flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="createToken" class="flex items-end gap-3">
        <flux:input wire:model="name" :label="__('Token name')" :placeholder="__('e.g. Accounting')" class="flex-1" required />
        <flux:button variant="primary" type="submit">{{ __('Create token') }}</flux:button>
    </form>

    @if ($this->tokens->isNotEmpty())
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Created') }}</flux:table.column>
                <flux:table.column>{{ __('Last used') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->tokens as $token)
                    <flux:table.row :key="$token->id">
                        <flux:table.cell>{{ $token->name }}</flux:table.cell>
                        <flux:table.cell>{{ $token->created_at->toDateString() }}</flux:table.cell>
                        <flux:table.cell>{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="danger" wire:click="revoke({{ $token->id }})" wire:confirm="{{ __('Revoke this token?') }}">{{ __('Revoke') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
