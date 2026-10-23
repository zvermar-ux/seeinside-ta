@extends('docs.layout')
@php $e = $entry; $n = fn ($v) => number_format((float) $v, 2, ',', '.'); $base = $agency->doc('base_currency'); @endphp
@section('content')
@foreach ([__('Original'), __('Copy')] as $copy)
<div style="border:1.5px solid #111;padding:10px 12px;margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div><b>{{ $agency->name }}</b><br><span class="small">{{ $agency->address }}, {{ $agency->city }}@if($agency->tax_number) · {{ __('Tax no.') }} {{ $agency->tax_number }}@endif</span></div>
        <div class="r"><div style="font-size:18px;font-weight:700">{{ $e->direction === 'in' ? __('CASH RECEIPT') : __('CASH PAYOUT') }} {{ __('no.') }} {{ $e->number() }}</div><span class="small muted">{{ $copy }} · {{ $agency->doc('registers.'.$e->register) ?? $e->register }}</span></div>
    </div>
    <table class="grid" style="margin-top:8px">
        <tr><td style="width:28%" class="muted">{{ $e->direction === 'in' ? __('Received from') : __('Paid to') }}</td><td class="b">{{ $e->party_name }}</td></tr>
        <tr><td class="muted">{{ __('Purpose') }}</td><td>{{ $e->purpose }}@if($e->category) <span class="small muted">· {{ \App\Models\CashEntry::categories()[$e->category] ?? $e->category }}</span>@endif</td></tr>
        <tr><td class="muted">{{ __('Amount') }}</td><td class="b" style="font-size:15px">{{ $n($e->amount) }} {{ $e->currency }}@if($e->currency !== $base) <span class="small" style="font-weight:400">= {{ $n($e->amount_base) }} {{ $base }} ({{ __('rate') }} {{ number_format((float) ($e->exchange_rate ?: $agency->eurRate()), 2, ',', '.') }})</span>@endif</td></tr>
        <tr><td class="muted">{{ __('Payment method') }}</td><td>{{ \App\Models\CashEntry::methods()[$e->method] ?? $e->method }}</td></tr>
        <tr><td class="muted">{{ __('Date') }}</td><td>{{ $e->entry_at?->format('d.m.Y H:i') }}</td></tr>
        @if($e->booking)<tr><td class="muted">{{ __('Booking') }}</td><td>{{ $e->booking->reference }}</td></tr>@endif
    </table>
    <div class="sign" style="grid-template-columns:1fr 1fr;margin-top:22px"><div>{{ __('Cashier') }}<div class="line"></div><span class="small muted">{{ $e->creator?->name }}</span></div><div>{{ $e->direction === 'in' ? __('Paid by') : __('Received by') }}<div class="line"></div></div></div>
</div>
@endforeach
@endsection
