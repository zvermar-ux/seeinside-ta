<x-filament-panels::page>
<style>
    .tp-bar { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem 1rem; }
    .tp-bar .tp-mark { margin-inline-start:auto; display:flex; align-items:center; gap:.4rem; font-size:.85rem; }
    .tp-in { width:6.2rem; border-radius:.45rem; border:1px solid rgba(0,0,0,.15); padding:.3rem .45rem; font-size:.875rem; background:#fff; }
    .tp-in.sm { width:4.2rem; padding:.15rem .35rem; font-size:.75rem; }
    .dark .tp-in { background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.12); color:inherit; }
    .tp-in.stop { background:#fee2e2; } .dark .tp-in.stop { background:rgba(239,68,68,.15); }
    .tp-wrap { overflow:auto; max-height:75vh; border-radius:.9rem; background:#fff; border:1px solid rgba(0,0,0,.07); }
    .dark .tp-wrap { background:rgb(var(--gray-900)); border-color:rgba(255,255,255,.08); }
    .tp { border-collapse:separate; border-spacing:0; font-size:.875rem; width:100%; }
    .tp th, .tp td { padding:.4rem .55rem; border-bottom:1px solid rgba(0,0,0,.06); text-align:left; vertical-align:top; }
    .dark .tp th, .dark .tp td { border-color:rgba(255,255,255,.06); }
    .tp thead th { position:sticky; top:0; z-index:2; background:inherit; background-color:#f9fafb; min-width:8.5rem; }
    .dark .tp thead th { background-color:rgb(var(--gray-800)); }
    .tp .first { position:sticky; left:0; z-index:1; background-color:#fff; white-space:nowrap; min-width:9rem; }
    .dark .tp .first { background-color:rgb(var(--gray-900)); }
    .tp thead .first { z-index:3; background-color:#f9fafb; } .dark .tp thead .first { background-color:rgb(var(--gray-800)); }
    .tp .muted { font-size:.72rem; color:rgb(var(--gray-500)); font-weight:400; }
    .tp tr.off td { opacity:.5; }
    .tp-fill { display:flex; gap:.25rem; align-items:center; margin-top:.3rem; }
    .tp-fill button { font-size:.8rem; color:rgb(var(--primary-600)); font-weight:600; }
    .tp-note { margin-top:.6rem; font-size:.78rem; color:rgb(var(--gray-500)); }
</style>
<div style="display:grid; gap:1rem">
    <x-filament::section>
        <div class="tp-bar">
            <x-filament::tabs>
                <x-filament::tabs.item :active="$mode === 'price'" wire:click="$set('mode', 'price')" icon="heroicon-m-tag">{{ __('Sale prices') }}</x-filament::tabs.item>
                <x-filament::tabs.item :active="$mode === 'cost_price'" wire:click="$set('mode', 'cost_price')" icon="heroicon-m-shopping-cart">{{ __('Purchase prices') }}</x-filament::tabs.item>
                <x-filament::tabs.item :active="$mode === 'stop_sale'" wire:click="$set('mode', 'stop_sale')" icon="heroicon-m-no-symbol">{{ __('Stop sale') }}</x-filament::tabs.item>
            </x-filament::tabs>
            <label style="display:flex; align-items:center; gap:.4rem; font-size:.85rem"><x-filament::input.checkbox wire:model.live="past" /> {{ __('Show past terms') }}</label>
            <div class="tp-mark" x-data="{ m: 20 }">
                <span>{{ __('Sale = purchase +') }}</span>
                <input type="number" class="tp-in sm" x-model="m"><span>%</span>
                <x-filament::button size="sm" color="gray" x-on:click="$wire.applyMarkup(m)">{{ __('Apply') }}</x-filament::button>
            </div>
        </div>
        <p class="tp-note">
            {{ $record->term_price_type === 'per_person' ? __('Prices are per person for the whole term.') : __('Prices are per room / apartment for the whole term.') }}
            {{ __('Empty cell = the room is not offered in that term. Ctrl+S saves.') }}
        </p>
    </x-filament::section>

    @if ($terms->isEmpty() || $rooms->isEmpty())
        <x-filament::section>{{ $rooms->isEmpty() ? __('Add rooms / apartments first.') : __('No terms yet – generate them in the “Terms” tab.') }}</x-filament::section>
    @else
    <div class="tp-wrap">
        <table class="tp">
            <thead>
                <tr>
                    <th class="first">{{ __('Term') }}</th>
                    @foreach ($rooms as $room)
                        <th>
                            <div>{{ $room->tr('name') }}</div>
                            <div class="muted">{{ $room->board?->value }} · {{ $room->max_adults }}+{{ $room->max_children }}</div>
                            @if ($mode !== 'stop_sale')
                                <div class="tp-fill" x-data="{ v: '' }">
                                    <input type="number" step="0.01" x-model="v" placeholder="{{ __('all') }}" class="tp-in sm">
                                    <button type="button" x-on:click="$wire.fillColumn({{ $room->id }}, v, true)" title="{{ __('Set for all terms') }}">↓ {{ __('all') }}</button>
                                </div>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach ($terms as $t)
                <tr @class(['off' => ! $t->is_active]) wire:key="t{{ $t->id }}">
                    <td class="first">
                        <div style="font-weight:600">{{ $t->date_from->format('d.m') }} – {{ $t->date_to->format('d.m.Y') }}</div>
                        <div class="muted">{{ $t->nights() }} {{ __('nights') }}{{ $t->label ? ' · '.$t->label : '' }}</div>
                    </td>
                    @foreach ($rooms as $room)
                        <td>
                            @if ($mode === 'stop_sale')
                                <label style="display:flex; align-items:center; gap:.3rem; font-size:.78rem"><x-filament::input.checkbox wire:model="cells.{{ $t->id }}.{{ $room->id }}.stop_sale" /> STOP</label>
                            @else
                                <input type="number" step="0.01" wire:model="cells.{{ $t->id }}.{{ $room->id }}.{{ $mode }}" @class(['tp-in', 'stop' => $cells[$t->id][$room->id]['stop_sale'] ?? false])>
                                @if ($mode === 'price' && ($cells[$t->id][$room->id]['cost_price'] ?? null))
                                    <div class="muted">{{ __('purch.') }} {{ $cells[$t->id][$room->id]['cost_price'] }}</div>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div><x-filament::button wire:click="save" icon="heroicon-m-check">{{ __('Save prices') }}</x-filament::button></div>
    @endif
</div>
</x-filament-panels::page>
