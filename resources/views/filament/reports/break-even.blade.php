@php
    $m = fn ($v) => \App\Services\Reports\OfferReport::money($v, $be['currency']);
    $done = $be['left'] <= 0 && $be['cost'] > 0;
    $compact = $compact ?? false;
@endphp
<div style="display:grid;gap:.55rem;font-size:.85rem">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;flex-wrap:wrap">
        <b style="font-size:{{ $compact ? '.9rem' : '1rem' }}">{{ $done ? __('Costs covered – the rest is profit') : __('Left to cover') }}</b>
        <span style="color:rgb(var(--gray-500));font-size:.75rem">{{ \Carbon\Carbon::parse($be['from'])->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($be['to'])->format('d.m.Y') }}</span>
    </div>
    <div style="font-size:{{ $compact ? '1.5rem' : '2rem' }};font-weight:700;line-height:1.1;color:rgb(var(--{{ $done ? 'success' : 'warning' }}-600))">
        {{ $done ? '+'.$m($be['profit_now']) : $m($be['left']) }}
    </div>
    <div style="position:relative;height:.7rem;border-radius:999px;background:rgba(var(--gray-500),.18);overflow:hidden" title="{{ __('booked') }} {{ $be['booked_pct'] }}% · {{ __('paid') }} {{ $be['covered_pct'] }}%">
        <i style="position:absolute;inset:0 auto 0 0;width:{{ $be['booked_pct'] }}%;background:rgba(var(--primary-500),.35);border-radius:inherit"></i>
        <i style="position:absolute;inset:0 auto 0 0;width:{{ $be['covered_pct'] }}%;background:rgb(var(--{{ $done ? 'success' : 'primary' }}-500));border-radius:inherit"></i>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:.2rem .8rem;color:rgb(var(--gray-600))">
        <span>{{ __('Total costs of the season') }}</span><b style="text-align:right">{{ $m($be['cost']) }}</b>
        <span>{{ __('Paid by clients') }} ({{ $be['covered_pct'] }}%)</span><b style="text-align:right">{{ $m($be['collected']) }}</b>
        <span>{{ __('Booked, not paid yet') }}</span><b style="text-align:right">{{ $m($be['owed']) }}</b>
        @if (! $done)
            <span>{{ __('Expected after all bookings are paid') }}</span><b style="text-align:right;color:rgb(var(--{{ $be['profit_expected'] >= 0 ? 'success' : 'danger' }}-600))">{{ $be['profit_expected'] >= 0 ? '+' : '' }}{{ $m($be['profit_expected']) }}</b>
        @endif
    </div>
    @if (! $done && $be['bookings_needed'] !== null && $be['left_booked'] > 0)
        <div style="padding:.5rem .7rem;border-radius:.6rem;background:rgba(var(--warning-500),.12);color:rgb(var(--warning-800))">
            {{ __('About :n more bookings needed to cover the costs', ['n' => $be['bookings_needed']]) }}
            <span style="opacity:.8">({{ __('average booking') }} {{ $m($be['avg_booking']) }})</span>
        </div>
    @elseif (! $done && $be['left_booked'] <= 0 && $be['cost'] > 0)
        <div style="padding:.5rem .7rem;border-radius:.6rem;background:rgba(var(--primary-500),.1)">{{ __('Bookings already cover the costs – :amount still to be collected.', ['amount' => $m($be['left'])]) }}</div>
    @elseif ($be['cost'] <= 0)
        <div style="color:rgb(var(--gray-500))">{{ __('No costs entered yet.') }}</div>
    @endif
</div>
