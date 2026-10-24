@extends('docs.layout')
@php
    $b = $booking; $item = $b->bookable; $hotel = $item instanceof \App\Models\Accommodation ? $item : null; $room = $b->room;
    $services = collect($b->priceLines())->filter(fn ($l) => $l['kind'] !== 'discount')->pluck('label')->all();
@endphp
@section('content')
@include('docs._header')
<div style="display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #111;padding-bottom:6px;margin-bottom:10px">
    <div style="font-size:22px;font-weight:700">ВАУЧЕР / VOUCHER</div>
    <div style="display:flex;align-items:flex-end;gap:12px">
        <div class="r"><span class="muted">{{ __('No.') }}</span> <b style="font-size:16px">{{ $b->reference }}</b><br><span class="muted">{{ __('Date') }}</span> {{ now()->format('d.m.Y') }}</div>
        <div class="c" style="line-height:1">{!! \App\Support\QrCode::svg($b->verifyUrl(), 92) !!}<div style="font-size:9px;color:#555;margin-top:2px">{{ __('Scan to check') }}</div></div>
    </div>
</div>
<table class="grid">
    <tr><td style="width:30%" class="muted">{{ __('To / Hotel') }}</td><td class="b">{{ $hotel?->name ?? $b->itemTitle() }}@if($hotel?->stars) {{ str_repeat('*', $hotel->stars) }}@endif<br><span class="small" style="font-weight:400">{{ trim(implode(', ', array_filter([$hotel?->address, $hotel?->place, $hotel?->country]))) }}</span></td></tr>
    <tr><td class="muted">{{ __('Arrangement') }}</td><td>{{ $b->itemTitle() }}</td></tr>
    <tr><td class="muted">{{ __('Check-in / Check-out') }}</td><td class="b">{{ $b->date_from?->format('d.m.Y') }} – {{ $b->date_to?->format('d.m.Y') }} <span style="font-weight:400">({{ $b->nights() }} {{ __('nights') }})</span></td></tr>
    <tr><td class="muted">{{ __('Room / unit') }}</td><td>{{ $room?->tr('name') }}@if($b->quantity > 1) × {{ $b->quantity }}@endif @if($b->room_number) · {{ __('Room no.') }} <b>{{ $b->room_number }}</b>@endif</td></tr>
    <tr><td class="muted">{{ __('Service') }}</td><td>{{ $room?->board?->getLabel() ?? '' }}</td></tr>
    <tr><td class="muted">{{ __('Persons') }}</td><td>{{ $b->adults }} {{ __('adults') }}@if($b->children) + {{ $b->children }} {{ __('children') }} ({{ implode(', ', (array) $b->children_ages) }})@endif</td></tr>
    <tr><td class="muted">{{ __('Guests') }}</td><td>@foreach ($b->passengerList() as $p){{ trim(($p['title'] ?? '').' '.mb_strtoupper(trim(($p['last_name'] ?? '').' '.($p['first_name'] ?? '')))) }}@if(!empty($p['birth_date'])) <span class="small muted">({{ \Carbon\Carbon::parse($p['birth_date'])->format('d.m.Y') }})</span>@endif<br>@endforeach</td></tr>
    @if ($services)<tr><td class="muted">{{ __('Services') }}</td><td>{{ implode(' · ', $services) }}</td></tr>@endif
    @if ($b->contract_note)<tr><td class="muted">{{ __('Note') }}</td><td style="white-space:pre-line">{{ $b->contract_note }}</td></tr>@endif
</table>
<p class="b" style="margin-top:12px">{{ __('Services are paid by the agency. Please provide the services listed above to the holder of this voucher.') }}</p>
<div class="sign" style="margin-top:30px"><div>{{ $agency->city }}, {{ now()->format('d.m.Y') }}</div>@if($stampUrl)<img class="stamp" src="{{ $stampUrl }}" alt="">@else<div></div>@endif<div>{{ __('For the agency') }}<div class="line"></div></div></div>
@endsection
