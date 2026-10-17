<?php

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Companies')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('operate');
    }

    #[Computed]
    public function companies(): LengthAwarePaginator
    {
        return Company::query()
            ->withCount(['users', 'invoices'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(20);
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Companies') }}</flux:heading>
        <flux:subheading>{{ __('Every customer of DVLA') }}</flux:subheading>
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:button size="sm" icon="eye" href="{{ url(config('telescope.path', 'telescope')) }}" target="_blank">Telescope</flux:button>
        <flux:button size="sm" icon="queue-list" href="{{ url(config('horizon.path', 'horizon')) }}" target="_blank">Horizon</flux:button>
        <flux:button size="sm" icon="chart-bar" href="{{ url(config('pulse.path', 'pulse')) }}" target="_blank">Pulse</flux:button>
        <flux:button size="sm" icon="document-text" href="{{ url(config('log-viewer.route_path', 'log-viewer')) }}" target="_blank">Log Viewer</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Company name')" class="max-w-md" />

    <flux:table :paginate="$this->companies">
        <flux:table.columns>
            <flux:table.column>{{ __('Company') }}</flux:table.column>
            <flux:table.column>{{ __('Tax ID') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Users') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Invoices') }}</flux:table.column>
            <flux:table.column>{{ __('Since') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->companies as $company)
                <flux:table.row :key="$company->id">
                    <flux:table.cell><flux:link :href="route('admin.company', $company)" wire:navigate>{{ $company->name }}</flux:link></flux:table.cell>
                    <flux:table.cell>{{ $company->tax_id }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $company->users_count }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $company->invoices_count }}</flux:table.cell>
                    <flux:table.cell>{{ $company->created_at->toDateString() }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
