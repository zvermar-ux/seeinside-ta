<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-key" :heading="__('Lease counters')" :description="__('Season costs minus payments received')">
        <div style="display:grid;gap:1rem">
            @foreach ($items as $it)
                @php($be = $it['be'])
                @php($done = $be['left'] <= 0 && $be['cost'] > 0)
                <a href="{{ $it['url'] }}" style="display:grid;gap:.3rem">
                    <div style="display:flex;justify-content:space-between;gap:.5rem;font-size:.85rem">
                        <b>{{ $it['a']->name }}</b>
                        <span style="font-weight:700;color:rgb(var(--{{ $done ? 'success' : 'warning' }}-600))">{{ $done ? '+'.\App\Services\Reports\OfferReport::money($be['profit_now'], $be['currency']) : __('left').' '.\App\Services\Reports\OfferReport::money($be['left'], $be['currency']) }}</span>
                    </div>
                    <div style="position:relative;height:.5rem;border-radius:999px;background:rgba(var(--gray-500),.18);overflow:hidden">
                        <i style="position:absolute;inset:0 auto 0 0;width:{{ $be['booked_pct'] }}%;background:rgba(var(--primary-500),.35)"></i>
                        <i style="position:absolute;inset:0 auto 0 0;width:{{ $be['covered_pct'] }}%;background:rgb(var(--{{ $done ? 'success' : 'primary' }}-500))"></i>
                    </div>
                    <span style="font-size:.72rem;color:rgb(var(--gray-500))">{{ $be['covered_pct'] }}% {{ __('paid') }} · {{ $be['booked_pct'] }}% {{ __('booked') }}@if(! $done && $be['bookings_needed']) · ≈ {{ $be['bookings_needed'] }} {{ __('more bookings needed') }}@endif</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
