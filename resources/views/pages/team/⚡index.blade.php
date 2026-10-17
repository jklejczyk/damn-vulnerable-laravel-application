<?php

use App\Enums\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Team')] class extends Component {
    public string $name = '';

    public string $email = '';

    public string $role = 'member';

    /** Shown once after adding a member. */
    public ?string $temporaryPassword = null;

    public function mount(): void
    {
        abort_if(Auth::user()->currentCompany() === null, 403, 'Choose a company in the operator panel first.');
        $this->authorize('manageMembers', Auth::user()->currentCompany());
    }

    #[Computed]
    public function members(): Collection
    {
        return Auth::user()->currentCompany()->users()->orderBy('name')->get();
    }

    public function addMember(): void
    {
        $company = Auth::user()->currentCompany();
        $this->authorize('manageMembers', $company);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $this->temporaryPassword = Str::password(16, symbols: false);

        $user = new User(['name' => $validated['name'], 'email' => $validated['email'], 'password' => $this->temporaryPassword]);
        $user->forceFill(['company_id' => $company->id, 'role' => Role::from($validated['role']), 'email_verified_at' => now()])->save();

        $this->reset('name', 'email', 'role');
        unset($this->members);
        Flux::toast(variant: 'success', text: __(':name added.', ['name' => $user->name]));
    }

    public function changeRole(int $userId, string $role): void
    {
        $company = Auth::user()->currentCompany();
        $this->authorize('manageMembers', $company);

        $member = $company->users()->findOrFail($userId);
        $newRole = Role::from($role);

        if ($member->isOwner() && $newRole !== Role::Owner && $this->lastOwner($member)) {
            Flux::toast(variant: 'danger', text: __('The company needs at least one owner.'));

            return;
        }

        $member->forceFill(['role' => $newRole])->save();
        unset($this->members);
    }

    public function removeMember(int $userId): void
    {
        $company = Auth::user()->currentCompany();
        $this->authorize('manageMembers', $company);

        $member = $company->users()->findOrFail($userId);

        if ($member->is(Auth::user()) || ($member->isOwner() && $this->lastOwner($member))) {
            Flux::toast(variant: 'danger', text: __('You cannot remove yourself or the last owner.'));

            return;
        }

        $member->tokens()->delete();
        $member->delete();
        unset($this->members);
        Flux::toast(text: __('Member removed.'));
    }

    private function lastOwner(User $owner): bool
    {
        return Auth::user()->currentCompany()->users()->where('role', Role::Owner)->whereKeyNot($owner->id)->doesntExist();
    }
}; ?>

<section class="w-full max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Team') }}</flux:heading>
        <flux:subheading>{{ __('People who work in :company', ['company' => auth()->user()->currentCompany()->name]) }}</flux:subheading>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->members as $member)
                <flux:table.row :key="$member->id">
                    <flux:table.cell>{{ $member->name }}</flux:table.cell>
                    <flux:table.cell>{{ $member->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:select size="sm" wire:change="changeRole({{ $member->id }}, $event.target.value)" :disabled="$member->is(auth()->user())">
                            @foreach (App\Enums\Role::cases() as $case)
                                <flux:select.option :value="$case->value" :selected="$member->role === $case">{{ $case->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @unless ($member->is(auth()->user()))
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeMember({{ $member->id }})" wire:confirm="{{ __('Remove :name?', ['name' => $member->name]) }}" :aria-label="__('Remove')" />
                        @endunless
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:card class="space-y-4">
        <flux:heading>{{ __('Add a member') }}</flux:heading>

        @if ($temporaryPassword)
            <flux:callout variant="success" icon="key" :heading="__('Temporary password')">
                <flux:callout.text>{{ __('Share it with the new member; they should change it after signing in.') }} <code class="font-mono">{{ $temporaryPassword }}</code></flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="addMember" class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" required />
            <flux:select wire:model="role" :label="__('Role')">
                @foreach (App\Enums\Role::cases() as $case)
                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="sm:col-span-3">
                <flux:button variant="primary" type="submit">{{ __('Add member') }}</flux:button>
            </div>
        </form>
    </flux:card>
</section>
