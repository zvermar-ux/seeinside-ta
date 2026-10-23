@extends('docs.layout')
@section('content')
@include('docs._header')
@php $m = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<h2 style="margin:4px 0">{{ __('Cash journal') }} – {{ $register }}</h2>
<p class="b" style="margin:0 0 8px">{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}@if ($to !== $from) – {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}@endif</p>
<table class="grid small">
    <tr style="background:#f3f4f6"><td class="b">{{ __('Date') }}</td><td class="b">{{ __('Document') }}</td><td class="b">{{ __('Company / person') }}</td><td class="b">{{ __('Purpose') }}</td><td class="b r">{{ __('Amount') }}</td><td class="b r">{{ __('Receipt') }} MKD</td><td class="b r">{{ __('Payout') }} MKD</td><td class="b r">{{ __('Balance') }}</td></tr>
    <tr><td colspan="7" class="b">{{ __('Opening balance') }}</td><td class="r b">{{ $m($opening) }}</td></tr>
    @foreach ($rows as $r)
        <tr>
            <td>{{ $r->entry_at->format('d.m.Y H:i') }}</td>
            <td>{{ $r->direction === 'in' ? __('Receipt') : __('Payout') }} {{ $r->number() }}</td>
            <td>{{ $r->party_name }}</td>
            <td>{{ $r->purpose }}</td>
            <td class="r">{{ $m($r->amount) }} {{ $r->currency }}</td>
            <td class="r">{{ $r->direction === 'in' ? $m($r->amount_base) : '' }}</td>
            <td class="r">{{ $r->direction === 'out' ? $m($r->amount_base) : '' }}</td>
            <td class="r">{{ $m($r->running) }}</td>
        </tr>
    @endforeach
    <tr style="background:#f3f4f6"><td colspan="5" class="b">{{ __('Total') }}</td><td class="r b">{{ $m($in) }}</td><td class="r b">{{ $m($out) }}</td><td class="r b">{{ $m($closing) }}</td></tr>
</table>
<p class="small muted" style="margin-top:6px">{{ __('Documents') }}: {{ $rows->count() }}</p>
<table style="margin-top:40px"><tr><td class="c" style="width:50%">______________________<br>{{ __('Cashier') }}</td><td class="c">______________________<br>{{ __('Controlled by') }}</td></tr></table>
@endsection
