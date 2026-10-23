@extends('docs.layout')
@section('content')
@include('docs._header')
<h2 style="margin:4px 0">{{ __('Rooming list') }}</h2>
<p class="b" style="margin:0 0 8px">{{ $heading }}</p>
<table class="grid small">
    <tr style="background:#f3f4f6"><td class="b">#</td><td class="b">{{ __('Booking') }}</td><td class="b">{{ __('Passengers') }}</td><td class="b">{{ __('Date of birth') }}</td><td class="b">{{ __('Passport no.') }}</td><td class="b">{{ __('Room') }}</td><td class="b">{{ __('Room no.') }}</td><td class="b">{{ __('Dates') }}</td><td class="b">{{ __('Phone') }}</td></tr>
    @php $k = 0; @endphp
    @foreach ($bookings as $b)
        @foreach ($b->passengerList() as $i => $p)
            <tr>
                <td>{{ ++$k }}</td>
                @if ($i === 0)<td rowspan="{{ count($b->passengerList()) }}">{{ $b->reference }}<br><span class="muted">{{ $b->status?->getLabel() }}</span></td>@endif
                <td>{{ mb_strtoupper(trim(($p['last_name'] ?? '').' '.($p['first_name'] ?? ''))) }}</td>
                <td>{{ ! empty($p['birth_date']) ? \Carbon\Carbon::parse($p['birth_date'])->format('d.m.Y') : '' }}</td>
                <td>{{ $p['passport'] ?? '' }}</td>
                @if ($i === 0)
                    <td rowspan="{{ count($b->passengerList()) }}">{{ $b->room?->tr('name') }}</td>
                    <td rowspan="{{ count($b->passengerList()) }}">{{ $b->room_number }}</td>
                    <td rowspan="{{ count($b->passengerList()) }}">{{ $b->date_from?->format('d.m') }}–{{ $b->date_to?->format('d.m.Y') }}</td>
                    <td rowspan="{{ count($b->passengerList()) }}">{{ $b->customer_phone }}</td>
                @endif
            </tr>
        @endforeach
    @endforeach
</table>
<p class="small muted" style="margin-top:6px">{{ __('Bookings') }}: {{ $bookings->count() }} · {{ __('Passengers') }}: {{ $k }}</p>
@endsection
