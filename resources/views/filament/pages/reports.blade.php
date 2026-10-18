@php
    use App\Services\Reports\OfferReport;
    use App\Services\Reports\GroupReport;
    $period = $this->period();
    $single = $period ? null : $this->single();
    $group = ($period || $single) ? null : $this->group();
    $m = fn ($v, $c = 'EUR') => OfferReport::money($v, $c);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $states = OfferReport::payStateLabels();
    $bookingUrl = fn ($id) => \App\Filament\Resources\BookingResource::getUrl('edit', ['record' => $id]);
@endphp
<x-filament-panels::page>
    <style>
        .rp-form { padding: 1.1rem 1.25rem; border-radius: .9rem; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.05); border: 1px solid rgba(0,0,0,.06); }
        .dark .rp-form, .dark .rp-card, .dark .rp-box { background: rgb(var(--gray-900)); border-color: rgba(255,255,255,.08); }
        .rp-run { display: flex; gap: .6rem; align-items: center; margin-top: 1rem; flex-wrap: wrap; }
        .rp-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: .5rem 1rem; }
        .rp-head h2 { font-size: 1.35rem; font-weight: 700; line-height: 1.25; }
        .rp-head p { color: rgb(var(--gray-500)); font-size: .85rem; }
        .rp-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: .75rem; }
        .rp-card { padding: .9rem 1rem; border-radius: .85rem; background: #fff; border: 1px solid rgba(0,0,0,.07); }
        .rp-card small { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: rgb(var(--gray-500)); font-weight: 600; }
        .rp-card b { display: block; font-size: 1.45rem; font-weight: 700; margin-top: .2rem; font-variant-numeric: tabular-nums; }
        .rp-card span { display: block; font-size: .78rem; color: rgb(var(--gray-500)); margin-top: .15rem; }
        .rp-card.is-good b { color: rgb(var(--success-600)); } .rp-card.is-bad b { color: rgb(var(--danger-600)); } .rp-card.is-warn b { color: rgb(var(--warning-600)); }
        .rp-box { border-radius: .9rem; background: #fff; border: 1px solid rgba(0,0,0,.07); overflow: hidden; }
        .rp-box > h3 { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: .85rem 1rem; font-weight: 650; border-bottom: 1px solid rgba(0,0,0,.06); }
        .rp-box > h3 small { font-weight: 400; color: rgb(var(--gray-500)); font-size: .8rem; }
        .rp-scroll { overflow-x: auto; }
        .rp-t { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .rp-t th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: rgb(var(--gray-500)); font-weight: 600; padding: .55rem .75rem; background: rgb(var(--gray-50)); white-space: nowrap; }
        .dark .rp-t th { background: rgb(var(--gray-800)); }
        .rp-t td { padding: .55rem .75rem; border-top: 1px solid rgba(0,0,0,.05); vertical-align: top; }
        .dark .rp-t td { border-color: rgba(255,255,255,.06); }
        .rp-t td:first-child { min-width: 11rem; }
        .rp-t .r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .rp-t tfoot td { font-weight: 700; background: rgb(var(--gray-50)); }
        .dark .rp-t tfoot td { background: rgb(var(--gray-800)); }
        .rp-t tr.is-click { cursor: pointer; } .rp-t tr.is-click:hover td { background: rgba(var(--primary-500), .06); }
        .rp-muted { color: rgb(var(--gray-500)); font-size: .78rem; }
        .rp-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .12rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; white-space: nowrap; }
        .rp-pill.paid, .rp-pill.overpaid { background: rgba(var(--success-500), .14); color: rgb(var(--success-700)); }
        .rp-pill.partial { background: rgba(var(--warning-500), .16); color: rgb(var(--warning-700)); }
        .rp-pill.unpaid { background: rgba(var(--danger-500), .13); color: rgb(var(--danger-700)); }
        .rp-pill.refunded { background: rgba(var(--gray-500), .15); color: rgb(var(--gray-600)); }
        .rp-bar { height: .45rem; border-radius: 999px; background: rgba(var(--gray-500), .18); overflow: hidden; min-width: 70px; }
        .rp-bar i { display: block; height: 100%; background: rgb(var(--primary-500)); border-radius: inherit; }
        .rp-bar i.hi { background: rgb(var(--success-500)); } .rp-bar i.full { background: rgb(var(--danger-500)); }
        .rp-tabs { display: flex; gap: .35rem; flex-wrap: wrap; }
        .rp-tabs button { padding: .25rem .7rem; border-radius: 999px; font-size: .78rem; font-weight: 600; border: 1px solid rgba(0,0,0,.1); background: transparent; }
        .rp-tabs button.on { background: rgb(var(--primary-500)); border-color: rgb(var(--primary-500)); color: #fff; }
        .rp-warn { padding: .7rem 1rem; border-radius: .7rem; background: rgba(var(--warning-500), .12); color: rgb(var(--warning-800)); font-size: .85rem; }
        .rp-pay { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .6rem; }
        .rp-empty { padding: 1.5rem 1rem; text-align: center; color: rgb(var(--gray-500)); font-size: .9rem; }
        .rp-print-head { display: none; }
        .rp-chart { display: flex; align-items: flex-end; gap: .35rem; height: 190px; padding: 1rem 1rem .4rem; border-bottom: 1px solid rgba(0,0,0,.06); }
        .rp-col { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; gap: .3rem; }
        .rp-bars { display: flex; align-items: flex-end; gap: 2px; height: 100%; width: 100%; justify-content: center; }
        .rp-bars i { display: block; width: 42%; max-width: 22px; border-radius: 4px 4px 0 0; min-height: 1px; }
        .rp-bars i.s { background: rgb(var(--primary-500)); } .rp-bars i.c { background: rgb(var(--gray-400)); }
        .rp-col small { font-size: .66rem; color: rgb(var(--gray-500)); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .rp-legend { display: flex; gap: 1rem; padding: .5rem 1rem; font-size: .75rem; color: rgb(var(--gray-600)); }
        .rp-legend span::before { content: ''; display: inline-block; width: .7rem; height: .7rem; border-radius: 3px; margin-right: .35rem; vertical-align: -1px; }
        .rp-legend .s::before { background: rgb(var(--primary-500)); } .rp-legend .c::before { background: rgb(var(--gray-400)); }
        .rp-delta { font-size: .72rem; font-weight: 600; } .rp-delta.up { color: rgb(var(--success-600)); } .rp-delta.down { color: rgb(var(--danger-600)); }
        .rp-grid2 { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 640px), 1fr)); }
        @media print {
            .fi-sidebar, .fi-topbar, .rp-form, .fi-header, .rp-tabs, .rp-noprint { display: none !important; }
            .fi-main { padding: 0 !important; max-width: none !important; } .fi-main-ctn, .fi-layout { margin: 0 !important; padding: 0 !important; }
            body { background: #fff !important; } .rp-print-head { display: block; font-size: .8rem; color: #555; margin-bottom: .4rem; }
            .rp-box, .rp-card { break-inside: avoid; box-shadow: none !important; } .rp-t { font-size: .72rem; } .rp-t tr { display: table-row !important; }
            .rp-kpis { grid-template-columns: repeat(4, 1fr); } .rp-card b { font-size: 1.05rem; }
        }
    </style>

    <form wire:submit="run" class="rp-form">
        {{ $this->form }}
        <div class="rp-run">
            <x-filament::button type="submit" icon="heroicon-m-chart-bar">{{ __('Show report') }}</x-filament::button>
            <x-filament::loading-indicator class="h-5 w-5" wire:loading wire:target="run" />
        </div>
    </form>

    @if ($period)
        @php($d = $period->build())
        @php($tt = $d['total'])
        @php($cur = $tt['currency'])
        @php($prev = in_array($this->applied['period'] ?? 'month', ['year', 'month', 'week', 'range'], true) ? $period->previousYear() : null)
        @php($delta = function ($now, $before) { if (! $before) return null; $p = round(($now - $before) / abs($before) * 100); return $p; })
        @php($max = max(1, collect($d['series'])->max(fn ($x) => max($x['revenue'], $x['cost']))))
        <div class="rp-print-head">{{ config('app.name') }} · {{ __('Reports') }} · {{ now()->format('d.m.Y H:i') }}</div>
        <div class="rp-head"><div>
            <p>{{ __('Agency report') }} · {{ ($this->applied['basis'] ?? 'travel') === 'booked' ? __('by booking date') : __('by travel date') }}</p>
            <h2>{{ $period->title() }}</h2>
            <p>{{ $d['offers'] }} {{ __('offers') }} · {{ __('Bookings counted') }}: {{ collect($this->applied['statuses'] ?? [])->map(fn ($s) => \App\Enums\BookingStatus::tryFrom($s)?->getLabel())->filter()->implode(', ') }}</p>
        </div></div>
        @if ($tt['mixed'])<div class="rp-warn">{{ __('Offers use different currencies; totals are added without conversion.') }}</div>@endif
        <div class="rp-kpis">
            @foreach ([['Sales', 'revenue', ''], ['Cost of the agency', 'cost', ''], ['Profit', 'profit', $tt['profit'] >= 0 ? 'is-good' : 'is-bad']] as [$lbl, $k, $cls])
                <div class="rp-card {{ $cls }}"><small>{{ __($lbl) }}</small><b>{{ $m($tt[$k], $cur) }}</b>
                    <span>@if($k === 'revenue'){{ $tt['bookings'] }} {{ __('bookings') }} · {{ $tt['pax'] }} {{ __('passengers') }}@elseif($k === 'profit'){{ $tt['margin'] !== null ? __('margin').' '.$num($tt['margin']).' %' : '—' }}@endif
                    @if($prev && ($dl = $delta($tt[$k], $prev[$k])) !== null) · <span class="rp-delta {{ ($k === 'cost' ? -$dl : $dl) >= 0 ? 'up' : 'down' }}" style="display:inline">{{ $dl >= 0 ? '▲' : '▼' }} {{ abs($dl) }}%</span> <span style="display:inline">{{ __('vs last year') }}</span>@endif</span></div>
            @endforeach
            <div class="rp-card"><small>{{ __('Payments received') }}</small><b>{{ $m($d['pay']['in'], $cur) }}</b><span>{{ __('by payment date') }}@if($d['pay']['out'] > 0) · {{ __('refunds') }} {{ $m($d['pay']['out'], $cur) }}@endif</span></div>
            <div class="rp-card {{ $tt['owed'] > 0 ? 'is-warn' : 'is-good' }}"><small>{{ __('Clients owe the agency') }}</small><b>{{ $m($tt['owed'], $cur) }}</b><span>{{ $tt['partial_count'] + $tt['unpaid_count'] }} {{ __('bookings with debt') }}</span></div>
            <div class="rp-card {{ $tt['cost_owed'] > 0 ? 'is-warn' : '' }}"><small>{{ __('Agency owes suppliers') }}</small><b>{{ $m($tt['cost_owed'], $cur) }}</b></div>
        </div>

        <section class="rp-box">
            <h3>{{ __('Sales and costs') }} <small>{{ __('hover a column for the numbers') }}</small></h3>
            <div class="rp-chart" role="img" aria-label="{{ __('Sales and costs') }}">
                @foreach ($d['series'] as $x)
                    <div class="rp-col" title="{{ $x['label'] }}&#10;{{ __('Sales') }}: {{ $m($x['revenue'], $cur) }}&#10;{{ __('Cost') }}: {{ $m($x['cost'], $cur) }}&#10;{{ __('Profit') }}: {{ $m($x['profit'], $cur) }}&#10;{{ __('Bookings') }}: {{ $x['bookings'] }}">
                        <div class="rp-bars"><i class="s" style="height:{{ round($x['revenue'] / $max * 100, 1) }}%"></i><i class="c" style="height:{{ round($x['cost'] / $max * 100, 1) }}%"></i></div>
                        <small>{{ $x['label'] }}</small>
                    </div>
                @endforeach
            </div>
            <div class="rp-legend"><span class="s">{{ __('Sales') }}</span><span class="c">{{ __('Cost') }}</span></div>
            <div class="rp-scroll"><table class="rp-t">
                <thead><tr><th>{{ __('Period') }}</th><th class="r">{{ __('Bookings') }}</th><th class="r">{{ __('Pax') }}</th><th class="r">{{ __('Sales') }}</th><th class="r">{{ __('Cost') }}</th><th class="r">{{ __('Profit') }}</th><th class="r">{{ __('Collected') }}</th><th class="r">{{ __('Clients owe') }}</th><th class="r">{{ __('Payments received') }}</th></tr></thead>
                <tbody>
                @foreach ($d['series'] as $x)
                    <tr @if(!$x['bookings'] && !$x['cost']) style="opacity:.55" @endif>
                        <td><b>{{ $x['label'] }}</b></td><td class="r">{{ $x['bookings'] }}</td><td class="r">{{ $x['pax'] }}</td>
                        <td class="r">{{ $m($x['revenue'], $cur) }}</td><td class="r">{{ $m($x['cost'], $cur) }}</td>
                        <td class="r"><b style="color:rgb(var(--{{ $x['profit'] >= 0 ? 'success' : 'danger' }}-600))">{{ $m($x['profit'], $cur) }}</b>@if($x['margin'] !== null)<div class="rp-muted">{{ $num($x['margin']) }} %</div>@endif</td>
                        <td class="r">{{ $m($x['collected'], $cur) }}</td><td class="r">{{ $m($x['owed'], $cur) }}</td><td class="r">{{ $m($x['pay']['in'], $cur) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td>{{ __('Total') }}</td><td class="r">{{ $tt['bookings'] }}</td><td class="r">{{ $tt['pax'] }}</td><td class="r">{{ $m($tt['revenue'], $cur) }}</td><td class="r">{{ $m($tt['cost'], $cur) }}</td><td class="r">{{ $m($tt['profit'], $cur) }}</td><td class="r">{{ $m($tt['collected'], $cur) }}</td><td class="r">{{ $m($tt['owed'], $cur) }}</td><td class="r">{{ $m($d['pay']['in'], $cur) }}</td></tr>
                @if ($prev)<tr><td class="rp-muted">{{ __('Same period last year') }}</td><td class="r rp-muted">{{ $prev['bookings'] }}</td><td class="r rp-muted">{{ $prev['pax'] }}</td><td class="r rp-muted">{{ $m($prev['revenue'], $cur) }}</td><td class="r rp-muted">{{ $m($prev['cost'], $cur) }}</td><td class="r rp-muted">{{ $m($prev['profit'], $cur) }}</td><td class="r rp-muted">{{ $m($prev['collected'], $cur) }}</td><td class="r rp-muted">{{ $m($prev['owed'], $cur) }}</td><td class="r rp-muted">{{ $m($prev['pay']['in'], $cur) }}</td></tr>@endif
                </tfoot>
            </table></div>
        </section>

        <div class="rp-grid2">
            @foreach ([['By employee (operator)', $d['byEmployee'], __('who handled the booking; the offer cost is shared by sales')], ['By tour operator', $d['byOperator'], __('supplier of the offer')], ['By offer type', $d['byType'], '']] as [$ttl, $list, $hint])
                <section class="rp-box">
                    <h3>{{ __($ttl) }} @if($hint)<small>{{ $hint }}</small>@endif</h3>
                    @if ($list)
                        <div class="rp-scroll"><table class="rp-t">
                            <thead><tr><th></th><th class="r">{{ __('Bookings') }}</th><th class="r">{{ __('Pax') }}</th><th class="r">{{ __('Sales') }}</th><th class="r">{{ __('Profit') }}</th><th class="r">{{ __('Clients owe') }}</th></tr></thead>
                            <tbody>
                            @foreach ($list as $x)
                                <tr><td><b>{{ $x['label'] }}</b></td><td class="r">{{ $x['bookings'] }}</td><td class="r">{{ $x['pax'] }}</td><td class="r">{{ $m($x['revenue'], $cur) }}</td>
                                    <td class="r"><span style="color:rgb(var(--{{ $x['profit'] >= 0 ? 'success' : 'danger' }}-600))">{{ $m($x['profit'], $cur) }}</span>@if($x['margin'] !== null)<div class="rp-muted">{{ $num($x['margin']) }} %</div>@endif</td>
                                    <td class="r">{{ $m($x['owed'], $cur) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @else
                        <div class="rp-empty">{{ __('No data') }}</div>
                    @endif
                </section>
            @endforeach
            <section class="rp-box">
                <h3>{{ __('Offers by profit') }} <small class="rp-noprint">{{ __('click for the detailed report') }}</small></h3>
                @if ($d['top'])
                    <div class="rp-scroll"><table class="rp-t">
                        <thead><tr><th>{{ __('Offer') }}</th><th class="r">{{ __('Pax') }}</th><th class="r">{{ __('Sales') }}</th><th class="r">{{ __('Cost') }}</th><th class="r">{{ __('Profit') }}</th></tr></thead>
                        <tbody>
                        @foreach (array_slice($d['top'], 0, 15) as $x)
                            <tr class="is-click" wire:click="openOffer('{{ $x['key'] }}', '{{ $period->from->toDateString() }}', '{{ $period->to->toDateString() }}')"><td><b>{{ $x['title'] }}</b><div class="rp-muted">{{ \App\Services\Reports\OfferReport::typeLabels()[$x['type']] ?? '' }}</div></td>
                                <td class="r">{{ $x['pax'] }}</td><td class="r">{{ $m($x['revenue'], $x['currency']) }}</td><td class="r">{{ $m($x['cost'], $x['currency']) }}</td>
                                <td class="r"><b style="color:rgb(var(--{{ $x['profit'] >= 0 ? 'success' : 'danger' }}-600))">{{ $m($x['profit'], $x['currency']) }}</b></td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    @if (count($d['top']) > 15)<p class="rp-muted" style="padding:.5rem 1rem">{{ __('All offers are in the Excel export.') }}</p>@endif
                @else
                    <div class="rp-empty">{{ __('No data') }}</div>
                @endif
            </section>
        </div>
    @elseif ($single)
        @php($t = $single->totals($lines = $single->costLines()))
        @php($cur = $single->currency())
        @php($av = $single->availability())
        @php($clients = $single->clients())
        <div class="rp-print-head">{{ config('app.name') }} · {{ __('Report') }} · {{ now()->format('d.m.Y H:i') }}</div>
        <div class="rp-head">
            <div>
                <p>{{ OfferReport::typeLabels()[$single->offer->getMorphClass()] ?? '' }}</p>
                <h2>{{ OfferReport::title($single->offer) }}</h2>
                <p>
                    @if ($this->applied['departure_id'] ?? null) {{ __('Departure') }} {{ optional(\App\Models\TourDeparture::find($this->applied['departure_id']))->start_date?->format('d.m.Y') }} · @endif
                    @if (($this->applied['from'] ?? null) || ($this->applied['to'] ?? null)) {{ __('Travel') }}: {{ $this->applied['from'] ? \Carbon\Carbon::parse($this->applied['from'])->format('d.m.Y') : '…' }} – {{ $this->applied['to'] ? \Carbon\Carbon::parse($this->applied['to'])->format('d.m.Y') : '…' }} · @endif
                    {{ __('Bookings counted') }}: {{ collect($this->applied['statuses'] ?? [])->map(fn ($s) => \App\Enums\BookingStatus::tryFrom($s)?->getLabel())->filter()->implode(', ') }}
                </p>
            </div>
            @if ($u = $single->editUrl())
                <x-filament::link :href="$u" icon="heroicon-m-pencil-square" class="rp-noprint">{{ __('Edit offer & costs') }}</x-filament::link>
            @endif
        </div>

        @if ($mixed = $single->mixedCurrencies())
            <div class="rp-warn">{{ __('Some amounts are in another currency (:list) and are added without conversion.', ['list' => implode(', ', $mixed)]) }}</div>
        @endif

        <div class="rp-kpis">
            <div class="rp-card"><small>{{ __('Sales') }}</small><b>{{ $m($t['revenue'], $cur) }}</b><span>{{ $t['bookings'] }} {{ __('bookings') }} · {{ $t['pax'] }} {{ __('passengers') }}</span></div>
            <div class="rp-card"><small>{{ __('Cost of the agency') }}</small><b>{{ $m($t['cost'], $cur) }}</b><span>@if($t['per_pax_cost'] !== null){{ $m($t['per_pax_cost'], $cur) }} {{ __('per passenger') }}@else{{ count($lines) }} {{ __('cost lines') }}@endif</span></div>
            <div class="rp-card {{ $t['profit'] >= 0 ? 'is-good' : 'is-bad' }}"><small>{{ __('Profit') }}</small><b>{{ $m($t['profit'], $cur) }}</b><span>{{ $t['margin'] !== null ? __('margin').' '.$num($t['margin']).' %' : '—' }}</span></div>
            <div class="rp-card"><small>{{ __('Collected from clients') }}</small><b>{{ $m($t['collected'], $cur) }}</b><span>{{ $t['revenue'] > 0 ? round($t['collected'] / $t['revenue'] * 100) : 0 }}% {{ __('of sales') }}</span></div>
            <div class="rp-card {{ $t['owed'] > 0 ? 'is-warn' : 'is-good' }}"><small>{{ __('Clients owe the agency') }}</small><b>{{ $m($t['owed'], $cur) }}</b><span>{{ $t['partial_count'] + $t['unpaid_count'] }} {{ __('bookings with debt') }}</span></div>
            <div class="rp-card {{ $t['cost_owed'] > 0 ? 'is-warn' : '' }}"><small>{{ __('Agency owes suppliers') }}</small><b>{{ $m($t['cost_owed'], $cur) }}</b><span>{{ __('paid') }} {{ $m($t['cost_paid'], $cur) }}</span></div>
            <div class="rp-card"><small>{{ __('Clients served') }}</small><b>{{ $t['pax'] }}</b><span>{{ $t['adults'] }} {{ __('adults') }} · {{ $t['children'] }} {{ __('children') }}</span></div>
            <div class="rp-card {{ $t['cash'] >= 0 ? '' : 'is-bad' }}"><small>{{ __('Cash balance') }}</small><b>{{ $m($t['cash'], $cur) }}</b><span>{{ __('collected − paid to suppliers') }}</span></div>
        </div>
        <div class="rp-pay">
            <span class="rp-pill paid">✓ {{ $t['paid_count'] }} {{ __('paid in full') }}</span>
            <span class="rp-pill partial">◐ {{ $t['partial_count'] }} {{ __('partly paid') }}</span>
            <span class="rp-pill unpaid">○ {{ $t['unpaid_count'] }} {{ __('not paid') }}</span>
            @if ($t['cancelled'])<span class="rp-pill refunded">✕ {{ $t['cancelled'] }} {{ __('cancelled') }}@if($t['refund_due'] > 0) · {{ __('to refund') }} {{ $m($t['refund_due'], $cur) }}@endif</span>@endif
        </div>

        @if ($single->isLeased())
            <section class="rp-box"><h3>{{ __('Lease counter') }} <small>{{ __('every payment reduces what is left of the season costs') }}</small></h3>
                <div style="padding:1rem">@include('filament.reports.break-even', ['be' => $single->breakEven()])</div></section>
        @endif

        {{-- availability --}}
        @if ($av['rows'])
            <section class="rp-box">
                <h3>{{ __('Availability') }} <small>@if(($av['kind'] ?? '') === 'rooms'){{ $av['period'] }} · {{ $av['nights'] }} {{ __('nights') }}@endif</small></h3>
                <div class="rp-scroll"><table class="rp-t">
                    <thead><tr>
                        <th>{{ match ($av['kind']) { 'departures' => __('Departure'), 'rooms' => __('Room / unit'), 'tickets' => __('Ticket'), default => '' } }}</th>
                        @if($av['kind'] === 'rooms')<th class="r">{{ __('Units') }}</th>@endif
                        <th class="r">{{ __('Capacity') }}</th><th class="r">{{ __('Sold') }}</th>@if($av['kind'] !== 'rooms')<th class="r">{{ __('Our bookings') }}</th>@endif<th class="r">{{ __('Free') }}</th><th style="width:22%">{{ __('Occupancy') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($av['rows'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            @if($av['kind'] === 'rooms')<td class="r">{{ $row['units'] }}</td>@endif
                            <td class="r">{{ $row['capacity'] ?? '∞' }} <span class="rp-muted">{{ $row['unit'] }}</span></td>
                            <td class="r">{{ $row['sold'] }}</td>
                            @if($av['kind'] !== 'rooms')<td class="r">{{ $row['ours'] }}</td>@endif
                            <td class="r"><b>{{ $row['free'] ?? '—' }}</b></td>
                            <td>@if($row['occupancy'] !== null)<div style="display:flex;align-items:center;gap:.5rem"><div class="rp-bar" style="flex:1"><i class="{{ $row['occupancy'] >= 100 ? 'full' : ($row['occupancy'] >= 70 ? 'hi' : '') }}" style="width:{{ min(100, $row['occupancy']) }}%"></i></div><span class="rp-muted">{{ $row['occupancy'] }}%</span></div>@else<span class="rp-muted">{{ __('no limit') }}</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </section>
        @endif

        {{-- costs --}}
        <section class="rp-box">
            <h3>{{ __('Costs of the agency') }} <small>{{ __('each line × the numbers from the bookings above') }}</small></h3>
            @if ($lines)
                <div class="rp-scroll"><table class="rp-t">
                    <thead><tr><th>{{ __('Cost item') }}</th><th>{{ __('Calculated') }}</th><th class="r">{{ __('Units') }}</th><th class="r">{{ __('Price') }}</th><th class="r">{{ __('Total') }}</th><th class="r">{{ __('Paid to supplier') }}</th><th class="r">{{ __('Owed to supplier') }}</th></tr></thead>
                    <tbody>
                    @foreach ($lines as $l)
                        <tr>
                            <td><b>{{ $l['name'] }}</b><div class="rp-muted">{{ $l['category'] }}@if($l['supplier']) · {{ $l['supplier'] }}@endif @if($l['scope']) · {{ $l['scope'] }}@endif @if($l['due'] && $l['owed'] > 0) · {{ __('due') }} {{ $l['due'] }}@endif</div></td>
                            <td class="rp-muted">{{ $l['basis'] }}</td>
                            <td class="r">{{ $l['basis_key'] === 'percent_revenue' ? $m($l['units'], $cur) : $num($l['units']) }} <span class="rp-muted">{{ $l['unit_label'] }}</span></td>
                            <td class="r">{{ $l['basis_key'] === 'percent_revenue' ? $num($l['rate']).' %' : $m($l['rate'], $l['currency']) }}@if($l['quantity'] != 1) × {{ $num($l['quantity']) }}@endif</td>
                            <td class="r"><b>{{ $m($l['total'], $l['currency']) }}</b></td>
                            <td class="r">{{ $m($l['paid'], $l['currency']) }}</td>
                            <td class="r" @if($l['owed'] > 0) style="color:rgb(var(--warning-700))" @endif>{{ $m($l['owed'], $l['currency']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="4">{{ __('Total cost') }}</td><td class="r">{{ $m($t['cost'], $cur) }}</td><td class="r">{{ $m($t['cost_paid'], $cur) }}</td><td class="r">{{ $m($t['cost_owed'], $cur) }}</td></tr></tfoot>
                </table></div>
            @else
                <div class="rp-empty">{{ __('No costs entered for this offer yet.') }} @if($u = $single->editUrl())<x-filament::link :href="$u.'?activeRelationManager=costs'" class="rp-noprint">{{ __('Add costs') }}</x-filament::link>@endif</div>
            @endif
        </section>

        {{-- passengers / clients --}}
        <section class="rp-box" x-data="{ f: 'all' }">
            <h3>
                <span>{{ __('Passengers and payments') }} <small>{{ count($clients) }} {{ __('bookings') }} · {{ $t['pax'] }} {{ __('passengers') }}</small></span>
                <span class="rp-tabs">
                    <button type="button" :class="{ on: f === 'all' }" @click="f = 'all'">{{ __('All') }}</button>
                    <button type="button" :class="{ on: f === 'debt' }" @click="f = 'debt'">{{ __('Owe') }} ({{ $t['partial_count'] + $t['unpaid_count'] }})</button>
                    <button type="button" :class="{ on: f === 'paid' }" @click="f = 'paid'">{{ __('Paid in full') }} ({{ $t['paid_count'] }})</button>
                    <button type="button" :class="{ on: f === 'partial' }" @click="f = 'partial'">{{ __('Partly paid') }} ({{ $t['partial_count'] }})</button>
                    <button type="button" :class="{ on: f === 'unpaid' }" @click="f = 'unpaid'">{{ __('Not paid') }} ({{ $t['unpaid_count'] }})</button>
                </span>
            </h3>
            @if ($clients)
                <div class="rp-scroll"><table class="rp-t">
                    <thead><tr><th>#</th><th>{{ __('Customer / passengers') }}</th><th>{{ __('Date') }}</th><th class="r">{{ __('Pax') }}</th><th>{{ __('Status') }}</th><th class="r">{{ __('Total') }}</th><th class="r">{{ __('Paid') }}</th><th class="r">{{ __('Owes') }}</th><th>{{ __('Payment') }}</th></tr></thead>
                    <tbody>
                    @foreach ($clients as $i => $c)
                        @php($grp = in_array($c['state'], ['paid', 'overpaid']) ? 'paid' : $c['state'])
                        <tr x-show="f === 'all' || f === '{{ $grp }}' || (f === 'debt' && {{ $c['owed'] > 0 ? 'true' : 'false' }})">
                            <td class="rp-muted">{{ $i + 1 }}</td>
                            <td>
                                <a href="{{ $bookingUrl($c['id']) }}" style="font-weight:600">{{ $c['customer'] }}</a> <span class="rp-muted">{{ $c['reference'] }}</span>
                                <div class="rp-muted">{{ implode(' · ', array_filter([$c['phone'], $c['email']])) }}</div>
                                @if ($c['passengers'])<div style="font-size:.8rem">{{ implode(', ', $c['passengers']) }}</div>@endif
                                @if ($c['room'])<div class="rp-muted">{{ $c['room'] }}</div>@endif
                            </td>
                            <td style="white-space:nowrap">{{ $c['date'] ?? '—' }}@if($c['nights'])<div class="rp-muted">{{ $c['nights'] }} {{ __('nights') }}</div>@endif</td>
                            <td class="r">{{ $c['pax'] }}@if($c['children'])<div class="rp-muted">{{ $c['adults'] }}+{{ $c['children'] }}</div>@endif</td>
                            <td class="rp-muted">{{ $c['status'] }}</td>
                            <td class="r">{{ $m($c['total'], $c['currency']) }}</td>
                            <td class="r">{{ $m($c['paid'], $c['currency']) }}@if($c['payments'] > 1)<div class="rp-muted">{{ $c['payments'] }} {{ __('payments') }}</div>@endif</td>
                            <td class="r"><b @if($c['owed'] > 0) style="color:rgb(var(--danger-600))" @endif>{{ $m($c['owed'], $c['currency']) }}</b>@if($c['over'] > 0)<div class="rp-muted">+{{ $m($c['over'], $c['currency']) }} {{ __('overpaid') }}</div>@endif</td>
                            <td><span class="rp-pill {{ $c['state'] }}">{{ $states[$c['state']] }}</span>@if($c['last_payment'])<div class="rp-muted">{{ __('last') }} {{ $c['last_payment'] }}</div>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="3">{{ __('Total') }}</td><td class="r">{{ $t['pax'] }}</td><td></td><td class="r">{{ $m($t['revenue'], $cur) }}</td><td class="r">{{ $m($t['collected'], $cur) }}</td><td class="r">{{ $m($t['owed'], $cur) }}</td><td></td></tr></tfoot>
                </table></div>
            @else
                <div class="rp-empty">{{ __('No bookings in this period / with these statuses.') }}</div>
            @endif
        </section>

        @if ($single->cancelled->isNotEmpty())
            <section class="rp-box">
                <h3>{{ __('Cancelled bookings') }} <small>{{ __('not counted in sales') }}</small></h3>
                <div class="rp-scroll"><table class="rp-t">
                    <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Date') }}</th><th class="r">{{ __('Pax') }}</th><th class="r">{{ __('Paid') }}</th><th>{{ __('Payment') }}</th></tr></thead>
                    <tbody>
                    @foreach ($single->cancelled as $b)
                        <tr><td><a href="{{ $bookingUrl($b->id) }}" style="font-weight:600">{{ $b->customer_name }}</a> <span class="rp-muted">{{ $b->reference }}</span></td>
                            <td>{{ OfferReport::travelDate($b)?->format('d.m.Y') ?? '—' }}</td><td class="r">{{ $b->passengerCount() }}</td>
                            <td class="r">{{ $m($b->paid_amount, $b->currency) }}</td>
                            <td>@if($b->payment_status === \App\Enums\PaymentStatus::Refunded)<span class="rp-pill refunded">{{ __('Refunded') }}</span>@elseif((float) $b->paid_amount > 0)<span class="rp-pill partial">{{ __('to refund') }}</span>@else<span class="rp-muted">—</span>@endif</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </section>
        @endif
    @elseif ($group)
        @php($rows = $group->rows())
        @php($tt = GroupReport::sum($rows))
        @php($cur = $tt['currency'])
        <div class="rp-print-head">{{ config('app.name') }} · {{ __('Group report') }} · {{ now()->format('d.m.Y H:i') }}</div>
        <div class="rp-head"><div><p>{{ __('Group report') }}</p><h2>{{ count($rows) }} {{ __('offers') }}</h2>
            <p>@if(($this->applied['from'] ?? null) || ($this->applied['to'] ?? null)){{ __('Travel') }}: {{ $this->applied['from'] ? \Carbon\Carbon::parse($this->applied['from'])->format('d.m.Y') : '…' }} – {{ $this->applied['to'] ? \Carbon\Carbon::parse($this->applied['to'])->format('d.m.Y') : '…' }}@endif
               @if($this->applied['place'] ?? null) · „{{ $this->applied['place'] }}“@endif</p></div></div>
        @if ($tt['mixed'])<div class="rp-warn">{{ __('Offers use different currencies; totals are added without conversion.') }}</div>@endif
        <div class="rp-kpis">
            <div class="rp-card"><small>{{ __('Sales') }}</small><b>{{ $m($tt['revenue'], $cur) }}</b><span>{{ $tt['bookings'] }} {{ __('bookings') }} · {{ $tt['pax'] }} {{ __('passengers') }}</span></div>
            <div class="rp-card"><small>{{ __('Cost of the agency') }}</small><b>{{ $m($tt['cost'], $cur) }}</b></div>
            <div class="rp-card {{ $tt['profit'] >= 0 ? 'is-good' : 'is-bad' }}"><small>{{ __('Profit') }}</small><b>{{ $m($tt['profit'], $cur) }}</b><span>{{ $tt['margin'] !== null ? __('margin').' '.$num($tt['margin']).' %' : '—' }}</span></div>
            <div class="rp-card"><small>{{ __('Collected from clients') }}</small><b>{{ $m($tt['collected'], $cur) }}</b></div>
            <div class="rp-card {{ $tt['owed'] > 0 ? 'is-warn' : 'is-good' }}"><small>{{ __('Clients owe the agency') }}</small><b>{{ $m($tt['owed'], $cur) }}</b><span>{{ $tt['partial_count'] + $tt['unpaid_count'] }} {{ __('bookings with debt') }}</span></div>
            <div class="rp-card {{ $tt['cost_owed'] > 0 ? 'is-warn' : '' }}"><small>{{ __('Agency owes suppliers') }}</small><b>{{ $m($tt['cost_owed'], $cur) }}</b></div>
        </div>
        <section class="rp-box">
            <h3>{{ __('By offer') }} <small class="rp-noprint">{{ __('click a row for the detailed report with passengers') }}</small></h3>
            @if ($rows)
                <div class="rp-scroll"><table class="rp-t">
                    <thead><tr><th>{{ __('Offer') }}</th><th class="r">{{ __('Bookings') }}</th><th class="r">{{ __('Pax') }}</th><th style="width:11%">{{ __('Occupancy') }}</th><th class="r">{{ __('Sales') }}</th><th class="r">{{ __('Cost') }}</th><th class="r">{{ __('Profit') }}</th><th class="r">{{ __('Collected') }}</th><th class="r">{{ __('Clients owe') }}</th><th class="r">{{ __('Owed to suppliers') }}</th><th>{{ __('Payments') }}</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $x)
                        <tr class="is-click" wire:click="openOffer('{{ $x['key'] }}')">
                            <td><b>{{ $x['title'] }}</b><div class="rp-muted">{{ $x['type'] }}@if($x['place']) · {{ $x['place'] }}@endif</div></td>
                            <td class="r">{{ $x['bookings'] }}</td><td class="r">{{ $x['pax'] }}</td>
                            <td>@if($x['occupancy'] !== null)<div class="rp-bar"><i class="{{ $x['occupancy'] >= 100 ? 'full' : ($x['occupancy'] >= 70 ? 'hi' : '') }}" style="width:{{ min(100, $x['occupancy']) }}%"></i></div><span class="rp-muted">{{ $x['occupancy'] }}% · {{ $x['sold'] }}/{{ $x['capacity'] }}</span>@else<span class="rp-muted">—</span>@endif</td>
                            <td class="r">{{ $m($x['revenue'], $x['currency']) }}</td>
                            <td class="r">{{ $m($x['cost'], $x['currency']) }}</td>
                            <td class="r"><b style="color:rgb(var(--{{ $x['profit'] >= 0 ? 'success' : 'danger' }}-600))">{{ $m($x['profit'], $x['currency']) }}</b>@if($x['margin'] !== null)<div class="rp-muted">{{ $num($x['margin']) }} %</div>@endif</td>
                            <td class="r">{{ $m($x['collected'], $x['currency']) }}</td>
                            <td class="r" @if($x['owed'] > 0) style="color:rgb(var(--danger-600))" @endif>{{ $m($x['owed'], $x['currency']) }}</td>
                            <td class="r">{{ $m($x['cost_owed'], $x['currency']) }}</td>
                            <td style="white-space:nowrap"><span class="rp-pill paid">{{ $x['paid_count'] }}</span> <span class="rp-pill partial">{{ $x['partial_count'] }}</span> <span class="rp-pill unpaid">{{ $x['unpaid_count'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td>{{ __('Total') }}</td><td class="r">{{ $tt['bookings'] }}</td><td class="r">{{ $tt['pax'] }}</td><td></td><td class="r">{{ $m($tt['revenue'], $cur) }}</td><td class="r">{{ $m($tt['cost'], $cur) }}</td><td class="r">{{ $m($tt['profit'], $cur) }}</td><td class="r">{{ $m($tt['collected'], $cur) }}</td><td class="r">{{ $m($tt['owed'], $cur) }}</td><td class="r">{{ $m($tt['cost_owed'], $cur) }}</td><td></td></tr></tfoot>
                </table></div>
                <p class="rp-muted" style="padding:.6rem 1rem"><span class="rp-pill paid">n</span> {{ __('paid in full') }} · <span class="rp-pill partial">n</span> {{ __('partly paid') }} · <span class="rp-pill unpaid">n</span> {{ __('not paid') }}</p>
            @else
                <div class="rp-empty">{{ __('No offers match these filters.') }}</div>
            @endif
        </section>
    @else
        <div class="rp-empty">{{ __('Choose an offer (or switch to group report), set the period and click “Show report”.') }}</div>
    @endif
</x-filament-panels::page>
