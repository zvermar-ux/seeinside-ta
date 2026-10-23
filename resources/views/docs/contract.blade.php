@extends('docs.layout')
@php
    use App\Services\Reports\OfferReport;
    $b = $booking; $item = $b->bookable; $cur = $b->currency ?: 'EUR';
    $lines = $b->priceLines();
    $base = collect($lines)->where('kind', 'base'); $extra = collect($lines)->where('kind', '!=', 'base');
    $total = $b->price_lines ? \App\Services\Bookings\BookingPricing::sum($lines) : (float) ($b->total ?? \App\Services\Bookings\BookingPricing::sum($lines));
    $paid = (float) $b->paid_amount; $left = max(0, round($total - $paid, 2));
    $n = fn ($v) => number_format((float) $v, 2, ',', '.');
    $type = match ($b->bookable_type) { 'tour' => __('Package arrangement'), 'accommodation' => __('Accommodation'), 'event' => __('Event'), 'charter' => __('Charter flight'), default => '' };
    $transport = match ($b->transport ?: ($item instanceof \App\Models\Tour ? ($item->is_flight ? 'flight' : ($item->tour_type?->value === 'flight' ? 'flight' : 'bus')) : ($b->bookable_type === 'charter' ? 'flight' : 'own'))) {
        'flight' => __('Plane'), 'bus' => __('Bus'), 'own' => __('Own transport'), 'none' => '—', default => $b->transport };
    $methods = $b->payments->pluck('method')->filter()->unique()->all();
    $hotel = $item instanceof \App\Models\Accommodation ? $item : null;
    $room = $b->room;
@endphp
@section('content')
<div class="head">
    <div>
        <div class="lic">{{ $agency->doc('license') }}@if($agency->license_number) <span class="small muted">{{ $agency->license_number }}</span>@endif</div>
        <div class="doc">{{ __('TRAVEL CONTRACT') }}</div>
        <div class="num">{{ $b->reference }}</div>
    </div>
    <div class="c">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else<b style="font-size:20px">{{ $agency->name }}</b>@endif</div>
    <div class="addr">{{ $agency->name }}<br>{{ $agency->address }}<br>{{ $agency->city }}@if($agency->phone)<br>{{ __('tel.') }} {{ $agency->phone }}@endif @if($agency->email)<br>{{ $agency->email }}@endif @if($agency->website)<br>{{ $agency->website }}@endif</div>
</div>

<h3>{{ __('Passengers') }}</h3>
<table class="box">
    @foreach ($b->passengerList() as $i => $p)
        <tr style="{{ $i ? 'border-top:3px double #111' : '' }}">
            <td style="width:24px" class="c b">{{ $i + 1 }}</td>
            <td style="width:34%"><span class="val">{{ trim(($p['title'] ?? '').' '.mb_strtoupper(trim(($p['last_name'] ?? '').' '.($p['first_name'] ?? '')))) }}</span><span class="cap">{{ __('Surname and name') }}</span></td>
            <td style="width:13%"><span class="val">{{ $transport }}</span><span class="cap">{{ __('Transport') }}</span></td>
            <td>
                <table><tr>
                    <td><span class="val">{{ $p['passport'] ?? '/' ?: '/' }}</span><span class="cap">{{ __('Passport no.') }}</span></td>
                    <td><span class="val">{{ $p['city'] ?? '' }}</span><span class="cap">{{ __('Place') }}</span></td>
                    <td><span class="val">{{ ! empty($p['birth_date']) ? \Carbon\Carbon::parse($p['birth_date'])->format('d.m.Y') : '' }}</span><span class="cap">{{ __('Date of birth') }}</span></td>
                </tr><tr>
                    <td colspan="2"><span class="val">{{ $p['address'] ?? '' }}</span><span class="cap">{{ __('Address') }}</span></td>
                    <td><span class="val">{{ $p['phone'] ?? ($i === 0 ? $b->customer_phone : '') }}</span><span class="cap">{{ __('Phone') }}</span></td>
                </tr></table>
            </td>
        </tr>
    @endforeach
</table>

<div class="two">
    <div>
        <h3>{{ __('Arrangement') }}</h3>
        <table class="box">
            <tr><td colspan="2"><span class="val">{{ $type }}</span><span class="cap">{{ __('Type of arrangement') }}</span></td></tr>
            <tr><td colspan="2"><span class="val">{{ $b->itemTitle() }}</span><span class="cap">{{ __('Name of the arrangement') }}</span></td></tr>
            <tr><td><span class="val">{{ $b->date_from?->format('d.m.Y') }}</span><span class="cap">{{ __('Departure date') }}</span></td>
                <td><span class="val">{{ $b->date_to?->format('d.m.Y') }}</span><span class="cap">{{ __('Return date') }}</span></td></tr>
            @if($b->departurePlace)<tr><td colspan="2"><span class="val">{{ $b->departurePlace->tr('name') }}</span><span class="cap">{{ __('Departure place') }}</span></td></tr>@endif
        </table>
    </div>
    <div>
        <h3>{{ __('Accommodation') }}</h3>
        <table class="box">
            <tr><td colspan="3"><span class="val">{{ $hotel?->name ?? ($item instanceof \App\Models\Tour ? ($item->location ?: '') : '') }}</span><span class="cap">{{ __('Object') }}</span></td></tr>
            <tr><td><span class="val">{{ $hotel ? trim(($hotel->place ?: '').' '.($hotel->type?->getLabel() ?? '')) : '' }}</span><span class="cap">{{ __('Type of accommodation') }}</span></td>
                <td><span class="val">{{ $room?->board?->value ?? ($hotel?->board_types[0] ?? '') }}</span><span class="cap">{{ __('Service') }}</span></td>
                <td><span class="val">{{ $hotel?->stars ? str_repeat('*', $hotel->stars) : '' }}</span><span class="cap">{{ __('Cat.') }}</span></td></tr>
            <tr><td colspan="2"><span class="val">{{ $room?->tr('name') }}@if($b->quantity > 1) × {{ $b->quantity }}@endif</span><span class="cap">{{ __('Room') }}</span></td>
                <td><span class="val">{{ $b->room_number }}</span><span class="cap">{{ __('Room no.') }}</span></td></tr>
        </table>
    </div>
</div>

<h3>{{ __('Payments') }}</h3>
<div class="two">
    <table class="box lines">
        <tr><td colspan="3" class="b">{{ __('Price calculation') }}:</td></tr>
        @foreach ($base as $l)
            <tr><td>{{ $l['label'] }}</td><td class="r">{{ rtrim(rtrim(number_format((float) $l['qty'], 2, ',', ''), '0'), ',') }} × {{ $n($l['price']) }}</td><td class="r">{{ $n($l['total']) }}</td></tr>
        @endforeach
        <tr class="sep"><td>{{ __('Basic price') }}:</td><td class="r b">{{ $cur }}</td><td class="r b">{{ $n($base->sum('total')) }}</td></tr>
        @if ($extra->isNotEmpty())
            <tr><td colspan="3" class="b">{{ __('Supplements / discounts') }}:</td></tr>
            @foreach ($extra as $l)
                <tr><td>{{ $l['label'] }}</td><td class="r">@if((float) $l['qty'] != 1){{ rtrim(rtrim(number_format((float) $l['qty'], 2, ',', ''), '0'), ',') }} × {{ $n($l['price']) }}@endif</td><td class="r">{{ $n($l['total']) }}</td></tr>
            @endforeach
        @endif
        <tr class="sep"><td class="b">{{ __('Total') }}:</td><td class="r b">{{ $cur }}</td><td class="r b">{{ $n($total) }}</td></tr>
        @if ($cur !== $agency->doc('base_currency'))
            <tr><td class="b">{{ __('Total') }} {{ $agency->doc('base_currency') }}:</td><td></td><td class="r b">{{ $n($b->inBase($total)) }}</td></tr>
        @endif
    </table>
    <div>
        <table class="box">
            <tr><td>
                <span class="chk">{{ in_array('cash', $methods, true) ? '✓' : '' }}</span>{{ __('Cash') }} &nbsp;
                <span class="chk">{{ $b->payments->count() > 1 ? '✓' : '' }}</span>{{ __('In instalments') }} &nbsp;
                <span class="chk">{{ in_array('other', $methods, true) ? '✓' : '' }}</span>{{ __('Other') }}<br>
                <span class="chk">{{ in_array('card', $methods, true) ? '✓' : '' }}</span>{{ __('Credit card') }}<br>
                <span class="chk">{{ in_array('bank', $methods, true) || $b->invoices->where('type', 'invoice')->isNotEmpty() ? '✓' : '' }}</span>{{ __('Invoice') }}
            </td></tr>
            <tr><td><table class="tot">
                <tr><td>{{ __('Paid') }}:</td><td class="r">{{ $n($paid) }} {{ $cur }}</td></tr>
                <tr><td>{{ __('To pay') }}:</td><td class="r b">{{ $n($left) }} {{ $cur }}</td></tr>
                @if ($cur !== $agency->doc('base_currency'))<tr class="big"><td>{{ __('To pay in') }} {{ $agency->doc('base_currency') }}:</td><td class="r">{{ $n($b->inBase($left)) }}</td></tr>@endif
                @if ($b->payment_due)<tr><td>{{ __('Pay by') }}:</td><td class="r b">{{ $b->payment_due->format('d.m.Y') }}</td></tr>@endif
            </table></td></tr>
        </table>
        <h3 style="margin-top:8px">{{ __('Note') }}</h3>
        <div class="box note" style="padding:4px 6px">{{ $b->contract_note }}</div>
    </div>
</div>

<p style="margin:8px 6px 2px">{{ $agency->doc('payment_clause') }}@if($cur !== $agency->doc('base_currency')) <span class="small muted">({{ __('rate') }} 1 {{ $cur }} = {{ number_format((float) ($b->exchange_rate ?: $agency->eurRate()), 2, ',', '.') }} {{ $agency->doc('base_currency') }})</span>@endif</p>
<p style="margin:2px 6px 8px">{{ $agency->doc('terms_clause') }}</p>

<div class="box" style="padding:8px 10px">
    <div class="c b" style="max-width:520px;margin:0 auto 10px">{{ $agency->doc('declaration') }}</div>
    <div class="sign">
        <div>{{ __('Place and date') }} <b>{{ ($b->created_at ?? now())->format('d.m.Y') }} {{ $agency->city }}</b></div>
        @if ($stampUrl)<img class="stamp" src="{{ $stampUrl }}" alt="">@else<div></div>@endif
        <div>{{ __('Passenger signature') }}<div class="line"></div></div>
    </div>
</div>
<div class="sign" style="grid-template-columns:1fr 1fr"><div>{{ __('Employee signature') }}<div class="line" style="max-width:200px"></div><span class="small muted">{{ $b->assignedUser?->name }}</span></div><div></div></div>

@if (filled($agency->doc('general_terms')))
    <div class="break"></div>
    <h3>{{ __('General terms of travel') }}</h3>
    <div class="small" style="white-space:pre-line">{{ $agency->doc('general_terms') }}</div>
@endif
@endsection
