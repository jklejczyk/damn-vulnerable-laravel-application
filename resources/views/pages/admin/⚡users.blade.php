<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $company = '';

    #[Url(except: '')]
    public string $role = '';

    public function mount(): void
    {
        $this->authorize('operate');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('company')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when($this->company === 'none', fn ($query) => $query->whereNull('company_id'))
            ->when(ctype_digit($this->company), fn ($query) => $query->where('company_id', (int) $this->company))
            ->when(Role::tryFrom($this->role), fn ($query, Role $role) => $query->whereNotNull('company_id')->where('role', $role))
            ->orderBy('email')
            ->paginate(25);
    }

    #[Computed]
    public function companies(): Collection
    {
        return Company::orderBy('name')->get(['id', 'name']);
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
        <flux:subheading>{{ __('Every account of every company') }}</flux:subheading>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Name or email')" />
        <flux:select wire:model.live="company">
            <flux:select.option value="">{{ __('All companies') }}</flux:select.option>
            <flux:select.option value="none">{{ __('No company (operators)') }}</flux:select.option>
            @foreach ($this->companies as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="role">
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach (App\Enums\Role::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Company') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('2FA') }}</flux:table.column>
            <flux:table.column>{{ __('Since') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->company)
                            <flux:link :href="route('admin.company', $user->company)" wire:navigate>{{ $user->company->name }}</flux:link>
                        @else
                            –
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($user->is_admin)
                            <flux:badge size="sm" color="purple">{{ __('Operator') }}</flux:badge>
                        @else
                            {{ $user->role->label() }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->two_factor_confirmed_at ? __('On') : __('Off') }}</flux:table.cell>
                    <flux:table.cell>{{ $user->created_at->toDateString() }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
