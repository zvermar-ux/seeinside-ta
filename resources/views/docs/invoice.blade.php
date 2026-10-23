@extends('docs.layout')
@php
    $inv = $invoice; $n = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
@section('content')
@include('docs._header')
<div class="two" style="margin-top:6px">
    <div class="box" style="padding:8px 10px">
        <div class="muted small">{{ __('Buyer') }}</div>
        <div class="b" style="font-size:14px">{{ $inv->client_name }}</div>
        <div>{{ $inv->client_address }}</div>
        @if($inv->client_tax_number)<div>{{ __('Tax no.') }} {{ $inv->client_tax_number }}</div>@endif
    </div>
    <div style="text-align:right">
        <div style="font-size:22px;font-weight:700">{{ $inv->type === 'proforma' ? __('PROFORMA INVOICE (OFFER)') : __('INVOICE') }}</div>
        <table class="tot" style="margin-left:auto;width:auto">
            <tr><td class="muted">{{ __('Number') }}:</td><td class="r b" style="font-size:15px">{{ $inv->number }}</td></tr>
            <tr><td class="muted">{{ __('Date') }}:</td><td class="r">{{ $inv->issue_date?->format('d.m.Y') }}</td></tr>
            @if($inv->due_date)<tr><td class="muted">{{ __('Payment due') }}:</td><td class="r b">{{ $inv->due_date->format('d.m.Y') }}</td></tr>@endif
            @if($inv->booking)<tr><td class="muted">{{ __('Booking') }}:</td><td class="r">{{ $inv->booking->reference }}</td></tr>@endif
        </table>
    </div>
</div>
<table class="grid" style="margin-top:12px">
    <tr style="background:#f3f4f6"><td class="c b" style="width:34px">{{ __('No.') }}</td><td class="b">{{ __('Description') }}</td><td class="r b" style="width:60px">{{ __('Qty') }}</td><td class="r b" style="width:100px">{{ __('Price') }}</td><td class="r b" style="width:55px">{{ __('VAT') }} %</td><td class="r b" style="width:110px">{{ __('Amount') }}</td></tr>
    @foreach ((array) $inv->lines as $i => $l)
        <tr><td class="c">{{ $i + 1 }}</td><td style="white-space:pre-line">{{ $l['description'] ?? '' }}</td><td class="r">{{ rtrim(rtrim(number_format((float) ($l['qty'] ?? 1), 2, ',', ''), '0'), ',') }}</td><td class="r">{{ $n($l['price'] ?? 0) }}</td><td class="r">{{ (float) ($l['vat'] ?? 0) }}</td><td class="r">{{ $n($l['total'] ?? 0) }}</td></tr>
    @endforeach
</table>
<table class="tot" style="width:300px;margin:8px 0 0 auto">
    <tr><td>{{ __('Base') }}:</td><td class="r">{{ $n($inv->subtotal) }}</td></tr>
    @if((float) $inv->discount)<tr><td>{{ __('Discount') }}:</td><td class="r">−{{ $n($inv->discount) }}</td></tr>@endif
    <tr><td>{{ __('VAT') }}:</td><td class="r">{{ $n($inv->vat_amount) }}</td></tr>
    <tr class="big"><td>{{ __('Total') }} {{ $inv->currency }}:</td><td class="r">{{ $n($inv->total) }}</td></tr>
    @if((float) $inv->paid_amount)<tr><td>{{ __('Paid') }}:</td><td class="r">{{ $n($inv->paid_amount) }}</td></tr><tr><td class="b">{{ __('To pay') }}:</td><td class="r b">{{ $n($inv->balance()) }}</td></tr>@endif
</table>
@if($inv->note)<p style="white-space:pre-line;margin-top:10px">{{ $inv->note }}</p>@endif
@if($agency->doc('invoice_footer'))<p class="small" style="white-space:pre-line;margin-top:10px">{{ $agency->doc('invoice_footer') }}</p>@endif
<p class="small">{{ __('Please pay to our bank account and quote the number of this document.') }} @if($agency->doc('bank_account')){{ __('Bank account') }}: <b>{{ $agency->doc('bank_account') }}</b> {{ $agency->doc('bank_name') }}@endif</p>
<div class="sign" style="margin-top:36px"><div>{{ __('Received by') }}<div class="line"></div></div>@if($stampUrl)<img class="stamp" src="{{ $stampUrl }}" alt="">@else<div></div>@endif<div>{{ __('Issued by') }}<div class="line"></div><span class="small muted">{{ $inv->creator?->name ?? '' }}</span></div></div>
@endsection
