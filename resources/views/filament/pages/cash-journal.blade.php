<x-filament-panels::page>
@php $m = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<style>
    .cj { display:grid; gap:1.25rem; }
    .cj-bar { display:flex; flex-wrap:wrap; align-items:flex-end; gap:.75rem; }
    .cj-bar label { display:grid; gap:.25rem; font-size:.85rem; font-weight:500; }
    .cj-bar .end { margin-inline-start:auto; }
    .cj-cards { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); }
    .cj-card { padding:1rem 1.2rem; border-radius:.9rem; background:#fff; border:1px solid rgba(0,0,0,.07); }
    .dark .cj-card, .dark .cj-box { background:rgb(var(--gray-900)); border-color:rgba(255,255,255,.08); }
    .cj-card .l { font-size:.82rem; color:rgb(var(--gray-500)); } .cj-card .v { font-size:1.45rem; font-weight:700; margin-top:.2rem; }
    .cj-card .v small { font-size:.8rem; font-weight:400; color:rgb(var(--gray-500)); }
    .cj .in { color:rgb(var(--success-600)); } .cj .out { color:rgb(var(--danger-600)); } .cj .pri { color:rgb(var(--primary-600)); } .cj .warn { color:rgb(var(--warning-600)); }
    .cj-box { border-radius:.9rem; background:#fff; border:1px solid rgba(0,0,0,.07); overflow-x:auto; }
    .cj-t { width:100%; font-size:.875rem; border-collapse:collapse; }
    .cj-t th { text-align:left; font-weight:500; color:rgb(var(--gray-500)); padding:.6rem .75rem; border-bottom:1px solid rgba(0,0,0,.08); white-space:nowrap; }
    .cj-t td { padding:.55rem .75rem; border-bottom:1px solid rgba(0,0,0,.05); }
    .dark .cj-t th, .dark .cj-t td { border-color:rgba(255,255,255,.06); }
    .cj-t .r { text-align:right; white-space:nowrap; } .cj-t .nw { white-space:nowrap; } .cj-t .b { font-weight:600; }
    .cj-t a { color:rgb(var(--primary-600)); } .cj-t a:hover { text-decoration:underline; }
    .cj-sum { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); }
    .cj-sum .row { display:flex; justify-content:space-between; gap:.5rem; padding:.2rem 0; font-size:.9rem; }
</style>
@if (! $agencyModel)
    <x-filament::section>{{ __('No agency') }}</x-filament::section>
@else
<div class="cj">
    <x-filament::section>
        <div class="cj-bar">
            @if ($agencies->isNotEmpty())
                <label><span>{{ __('Agency') }}</span>
                    <x-filament::input.wrapper><x-filament::input.select wire:model.live="agency">
                        @foreach ($agencies as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </x-filament::input.select></x-filament::input.wrapper></label>
            @endif
            <label><span>{{ __('Cash register') }}</span>
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="register">
                    @foreach ($registers as $k => $name)<option value="{{ $k }}">{{ $name }}</option>@endforeach
                </x-filament::input.select></x-filament::input.wrapper></label>
            <label><span>{{ __('From') }}</span><x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper></label>
            <label><span>{{ __('To') }}</span><x-filament::input.wrapper><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper></label>
            <x-filament::icon-button color="gray" icon="heroicon-m-chevron-left" wire:click="shift(-1)" :label="__('Previous day')" />
            <x-filament::icon-button color="gray" icon="heroicon-m-chevron-right" wire:click="shift(1)" :label="__('Next day')" />
            <x-filament::button color="gray" size="sm" wire:click="preset('today')">{{ __('Today') }}</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="preset('week')">{{ __('This week') }}</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="preset('month')">{{ __('This month') }}</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="preset('year')">{{ __('This year') }}</x-filament::button>
            <span class="end"><x-filament::button tag="a" :href="$printUrl" target="_blank" icon="heroicon-m-printer">{{ __('Print') }}</x-filament::button></span>
        </div>
    </x-filament::section>

    <div class="cj-cards">
        @foreach ([[__('Opening balance'), $journal['opening'], ''], [__('Receipts'), $journal['in'], 'in'], [__('Payouts'), $journal['out'], 'out'], [__('Closing balance'), $journal['closing'], 'pri']] as [$label, $value, $cls])
            <div class="cj-card"><div class="l">{{ $label }}</div><div class="v {{ $cls }}">{{ $m($value) }} <small>MKD</small></div></div>
        @endforeach
    </div>

    <div class="cj-box">
        <table class="cj-t">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Document') }}</th><th>{{ __('Company / person') }}</th><th>{{ __('Purpose') }}</th>
                <th class="r">{{ __('Amount') }}</th><th class="r">{{ __('Receipt') }} MKD</th><th class="r">{{ __('Payout') }} MKD</th><th class="r">{{ __('Balance') }}</th></tr></thead>
            <tbody>
            <tr><td colspan="7" class="b">{{ __('Opening balance') }}</td><td class="r b">{{ $m($journal['opening']) }}</td></tr>
            @forelse ($journal['rows'] as $r)
                <tr>
                    <td class="nw">{{ $r->entry_at->format('d.m.Y H:i') }}</td>
                    <td class="nw"><a href="{{ \App\Filament\Resources\CashEntryResource::getUrl('edit', ['record' => $r]) }}">{{ $r->direction === 'in' ? __('Receipt') : __('Payout') }} {{ $r->number() }}</a></td>
                    <td>{{ $r->party_name }}</td>
                    <td style="color:rgb(var(--gray-500))">{{ \Illuminate\Support\Str::limit($r->purpose, 60) }}</td>
                    <td class="r">{{ $m($r->amount) }} {{ $r->currency }}</td>
                    <td class="r in">{{ $r->direction === 'in' ? $m($r->amount_base) : '' }}</td>
                    <td class="r out">{{ $r->direction === 'out' ? $m($r->amount_base) : '' }}</td>
                    <td class="r">{{ $m($r->running) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; padding:1.5rem; color:rgb(var(--gray-500))">{{ __('No cash documents in this period.') }}</td></tr>
            @endforelse
            <tr><td colspan="5" class="b">{{ __('Total') }}</td><td class="r b in">{{ $m($journal['in']) }}</td><td class="r b out">{{ $m($journal['out']) }}</td><td class="r b">{{ $m($journal['closing']) }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="cj-sum">
        <x-filament::section :heading="__('Clients owe (bookings)')" compact>
            @forelse ($debtors as $d)<div class="row"><span>{{ $d->n }} {{ __('bookings') }}</span><b class="out">{{ $m($d->s) }} {{ $d->currency }}</b></div>
            @empty<div style="color:rgb(var(--gray-500))">—</div>@endforelse
        </x-filament::section>
        <x-filament::section :heading="__('Unpaid invoices')" compact>
            @forelse ($invoices as $d)<div class="row"><span>{{ $d->n }} {{ __('invoices') }}@if ($d->overdue > 0) · <span class="out">{{ __('overdue') }} {{ $m($d->overdue) }}</span>@endif</span><b>{{ $m($d->s) }} {{ $d->currency }}</b></div>
            @empty<div style="color:rgb(var(--gray-500))">—</div>@endforelse
        </x-filament::section>
        <x-filament::section :heading="__('We owe suppliers')" compact>
            @forelse ($suppliers as $d)<div class="row"><span>{{ $d->n }} {{ __('invoices') }}</span><b class="warn">{{ $m($d->s) }} {{ $d->currency }}</b></div>
            @empty<div style="color:rgb(var(--gray-500))">—</div>@endforelse
        </x-filament::section>
    </div>
</div>
@endif
</x-filament-panels::page>
