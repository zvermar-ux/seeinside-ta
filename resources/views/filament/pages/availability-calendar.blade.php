@php
    $locale = app()->getLocale();
    $dates = $data['dates'] ?? [];
    $checkoutDates = array_merge($dates, [$to->copy()->addDay()->toDateString()]);
    $roomsJs = collect($data['rooms'] ?? [])->map(fn ($r) => [
        'name' => $r['name'],
        'free' => array_values(array_map(fn ($d) => $d['free'], $r['days'])),
    ]);
    $bookings = collect($data['rooms'] ?? [])->flatMap(fn ($r) => collect($r['days'])->flatMap(fn ($d) => collect($d['bookings'])->map(fn ($b) => $b + ['room' => $r['name']])))
        ->unique('id')->sortBy('from')->values();
    $statusLabel = fn ($s) => \App\Enums\BookingStatus::tryFrom((string) $s)?->getLabel() ?? $s;
@endphp

<x-filament-panels::page>
    <style>
        .ta-cal-toolbar { display:flex; flex-wrap:wrap; gap:.75rem; align-items:end; justify-content:space-between; }
        .ta-cal-toolbar .grow { flex:1 1 280px; max-width:520px; }
        .ta-cal-nav { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
        .ta-cal-month { font-weight:600; min-width:10rem; text-align:center; text-transform:capitalize; }
        .ta-cal-legend { display:flex; flex-wrap:wrap; gap:1rem; font-size:.8rem; color:rgb(var(--gray-500)); }
        .ta-cal-legend span { display:inline-flex; align-items:center; gap:.35rem; }
        .ta-cal-legend i { width:.9rem; height:.9rem; border-radius:.25rem; display:inline-block; }
        .ta-cal-wrap { overflow-x:auto; border-radius:.75rem; border:1px solid rgba(var(--gray-200),1); background:#fff; }
        .dark .ta-cal-wrap { border-color:rgba(255,255,255,.1); background:rgba(255,255,255,.02); }
        .ta-cal { border-collapse:separate; border-spacing:0; font-size:.78rem; width:max-content; min-width:100%; }
        .ta-cal th, .ta-cal td { padding:0; text-align:center; border-bottom:1px solid rgba(var(--gray-200),1); }
        .dark .ta-cal th, .dark .ta-cal td { border-color:rgba(255,255,255,.07); }
        .ta-cal thead th { padding:.4rem 0; font-weight:500; color:rgb(var(--gray-500)); min-width:2.4rem; line-height:1.15; }
        .ta-cal thead th b { display:block; font-size:.9rem; color:rgb(var(--gray-900)); }
        .dark .ta-cal thead th b { color:#fff; }
        .ta-cal thead th.is-weekend { background:rgba(var(--gray-500),.06); }
        .ta-cal thead th.is-today b { color:#fff; background:rgb(var(--primary-600)); border-radius:999px; width:1.6rem; height:1.6rem; line-height:1.6rem; margin:0 auto; }
        .ta-cal .ta-cal-room { position:sticky; left:0; z-index:2; background:#fff; text-align:left; padding:.5rem .75rem; min-width:13rem; max-width:16rem; border-right:1px solid rgba(var(--gray-200),1); }
        .dark .ta-cal .ta-cal-room { background:rgb(var(--gray-900)); border-color:rgba(255,255,255,.1); }
        .ta-cal-room .name { font-weight:600; color:rgb(var(--gray-950)); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .dark .ta-cal-room .name { color:#fff; }
        .ta-cal-room .meta { color:rgb(var(--gray-500)); font-size:.72rem; }
        .ta-cal-cell { height:2.6rem; cursor:pointer; position:relative; border-left:1px solid rgba(255,255,255,.7); transition:filter .1s, box-shadow .1s; font-weight:600; user-select:none; }
        .dark .ta-cal-cell { border-left-color:rgba(0,0,0,.35); }
        .ta-cal-cell:hover { filter:brightness(.93); }
        .ta-cal-free { background:#dcfce7; color:#166534; }
        .ta-cal-partial { background:#fef9c3; color:#854d0e; }
        .ta-cal-held { background:#fed7aa; color:#9a3412; }
        .ta-cal-full { background:#fecaca; color:#991b1b; }
        .dark .ta-cal-free { background:rgba(34,197,94,.22); color:#86efac; }
        .dark .ta-cal-partial { background:rgba(234,179,8,.22); color:#fde047; }
        .dark .ta-cal-held { background:rgba(249,115,22,.25); color:#fdba74; }
        .dark .ta-cal-full { background:rgba(239,68,68,.25); color:#fca5a5; }
        .ta-cal-past { opacity:.45; cursor:default; }
        .ta-cal-sel { box-shadow:inset 0 0 0 2px rgb(var(--primary-600)); background:rgba(var(--primary-500),.28) !important; color:rgb(var(--primary-900)) !important; }
        .dark .ta-cal-sel { color:#fff !important; }
        .ta-cal-cell .dot { position:absolute; bottom:3px; left:50%; transform:translateX(-50%); width:4px; height:4px; border-radius:99px; background:currentColor; opacity:.7; }
        .ta-cal-cell .ext { position:absolute; top:2px; right:3px; font-size:.55rem; opacity:.7; }
        .ta-cal-bar { position:sticky; bottom:1rem; z-index:20; display:flex; flex-wrap:wrap; gap:.75rem; align-items:center; justify-content:space-between; padding:.75rem 1rem; border-radius:.75rem; background:rgb(var(--gray-950)); color:#fff; box-shadow:0 10px 30px rgba(0,0,0,.25); }
        .ta-cal-bar .muted { color:rgba(255,255,255,.65); font-size:.8rem; }
        .ta-cal-notice { padding:.65rem .9rem; border-radius:.6rem; font-size:.85rem; background:rgba(var(--warning-500),.12); color:rgb(var(--warning-700)); }
        .dark .ta-cal-notice { color:rgb(var(--warning-400)); }
        .ta-cal-ok { background:rgba(var(--success-500),.12); color:rgb(var(--success-700)); }
        .ta-cal-list { width:100%; font-size:.85rem; }
        .ta-cal-list th { text-align:left; color:rgb(var(--gray-500)); font-weight:500; padding:.4rem .5rem; }
        .ta-cal-list td { padding:.45rem .5rem; border-top:1px solid rgba(var(--gray-200),1); }
        .dark .ta-cal-list td { border-color:rgba(255,255,255,.07); }
    </style>

    <div class="ta-cal-toolbar">
        <div class="grow">
            <label class="fi-fo-field-wrp-label" style="display:block;font-size:.875rem;font-weight:500;margin-bottom:.4rem">{{ __('Hotel / apartment') }}</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="accommodationId">
                    <option value="">— {{ __('Choose') }} —</option>
                    @foreach ($accommodations as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}{{ $a->place ? ' · '.$a->place : '' }}{{ $a->supplier_connection_id ? ' · API' : '' }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div class="ta-cal-nav">
            <x-filament::icon-button icon="heroicon-m-chevron-left" wire:click="shift(-1)" :label="__('Previous month')" color="gray" />
            <div class="ta-cal-month">{{ $from->copy()->locale($locale)->isoFormat('D MMM') }} – {{ $to->copy()->locale($locale)->isoFormat('D MMM YYYY') }}</div>
            <x-filament::icon-button icon="heroicon-m-chevron-right" wire:click="shift(1)" :label="__('Next month')" color="gray" />
            <x-filament::button size="sm" color="gray" wire:click="goToday">{{ __('Today') }}</x-filament::button>
            <x-filament::input.wrapper style="width:10.5rem">
                <x-filament::input type="date" wire:model.live.debounce.500ms="start" />
            </x-filament::input.wrapper>
        </div>
    </div>

    <div class="ta-cal-legend">
        <span><i class="ta-cal-free"></i>{{ __('Free') }}</span>
        <span><i class="ta-cal-partial"></i>{{ __('Partly booked') }}</span>
        <span><i class="ta-cal-held"></i>{{ __('On hold (inquiry / pending)') }}</span>
        <span><i class="ta-cal-full"></i>{{ __('Booked') }}</span>
        <span>{{ __('The number = free units that night. Click the arrival and departure night to book.') }}</span>
    </div>

    @if (! $accommodation)
        <x-filament::section>
            <p style="color:rgb(var(--gray-500))">{{ __('Choose a hotel or apartment. Rooms are added in Hotels & apartments → Rooms & price list.') }}</p>
        </x-filament::section>
    @else
        @if ($data['notice'])
            <div class="ta-cal-notice">{{ $data['notice'] }}</div>
        @elseif ($data['source'] === 'supplier')
            <div class="ta-cal-notice ta-cal-ok">{{ __('Live availability from :name (refreshed every 5 minutes). Rooms marked with ⇄ use the booking system.', ['name' => $data['supplier']]) }}</div>
        @endif

        @if (empty($data['rooms']))
            <x-filament::section>
                <p style="color:rgb(var(--gray-500))">{{ __('This property has no rooms yet.') }}
                    @if ($editAccommodationUrl)<a href="{{ $editAccommodationUrl }}" style="color:rgb(var(--primary-600));font-weight:600">{{ __('Add rooms') }} →</a>@endif
                </p>
            </x-filament::section>
        @else
            <div
                wire:key="cal-{{ $accommodation->id }}-{{ $from->toDateString() }}"
                x-data="{
                    rooms: @js($roomsJs),
                    dates: @js($checkoutDates),
                    today: @js($today),
                    room: null, a: null, b: null,
                    pick(roomId, i) {
                        if (this.dates[i] < this.today) return;
                        if (this.room !== roomId || this.a === null || this.b !== null || i < this.a) {
                            this.room = roomId; this.a = i; this.b = null;
                            return;
                        }
                        this.b = i;
                    },
                    sel(roomId, i) {
                        if (this.room !== roomId || this.a === null) return false;
                        const end = this.b ?? this.a;
                        return i >= this.a && i <= end;
                    },
                    get last() { return this.b ?? this.a },
                    get nights() { return this.a === null ? 0 : this.last - this.a + 1 },
                    get minFree() {
                        if (this.a === null) return 0;
                        return Math.min(...this.rooms[this.room].free.slice(this.a, this.last + 1));
                    },
                    fmt(d) { const [y, m, dd] = d.split('-'); return dd + '.' + m + '.' + y },
                    url() {
                        const p = new URLSearchParams({
                            bookable_type: 'accommodation',
                            bookable_id: @js($accommodation->id),
                            accommodation_room_id: this.room,
                            date_from: this.dates[this.a],
                            date_to: this.dates[this.last + 1],
                        });
                        return @js($bookingUrl) + '?' + p.toString();
                    },
                }"
                x-on:keydown.escape.window="room = null; a = null; b = null"
                style="display:flex;flex-direction:column;gap:1rem"
            >
                <div class="ta-cal-wrap">
                    <table class="ta-cal">
                        <thead>
                            <tr>
                                <th class="ta-cal-room">{{ __('Room / apartment') }}</th>
                                @foreach ($dates as $date)
                                    @php $d = \Carbon\Carbon::parse($date)->locale($locale); @endphp
                                    <th @class(['is-weekend' => $d->isWeekend(), 'is-today' => $date === $today])>
                                        {{ $d->isoFormat('dd') }}<b>{{ $d->day }}</b>{{ $d->day === 1 || $loop->first ? $d->isoFormat('MMM') : '' }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['rooms'] as $room)
                                <tr>
                                    <th class="ta-cal-room">
                                        <div class="name" title="{{ $room['name'] }}">{{ $room['name'] }}</div>
                                        <div class="meta">
                                            {{ $room['board'] ? strtoupper($room['board']).' · ' : '' }}{{ trans_choice(':count unit|:count units', $room['units'], ['count' => $room['units']]) }}{{ $room['external'] ? ' · ⇄ '.$data['supplier'] : '' }}
                                        </div>
                                    </th>
                                    @foreach ($room['days'] as $date => $day)
                                        @php
                                            $state = $day['free'] <= 0
                                                ? ($day['booked'] === 0 && $day['held'] > 0 ? 'held' : 'full')
                                                : ($day['free'] < $day['units'] ? 'partial' : 'free');
                                            $tip = \Carbon\Carbon::parse($date)->format('d.m.Y').' · '.__('Free').': '.$day['free'].'/'.$day['units']
                                                .($day['price'] !== null ? ' · '.number_format($day['price'], 0, ',', '.').' '.$accommodation->currency : ' · '.__('price on request'))
                                                .collect($day['bookings'])->map(fn ($b) => "\n".$b['reference'].' – '.$b['customer'].' ('.$statusLabel($b['status']).')')->implode('');
                                            $firstBooking = $day['bookings'][0]['id'] ?? null;
                                        @endphp
                                        <td
                                            class="ta-cal-cell ta-cal-{{ $state }} @if ($date < $today) ta-cal-past @endif"
                                            title="{{ $tip }}"
                                            x-bind:class="{ 'ta-cal-sel': sel({{ $room['id'] }}, {{ $loop->index }}) }"
                                            @if ($day['free'] > 0 && $bookingUrl)
                                                x-on:click="pick({{ $room['id'] }}, {{ $loop->index }})"
                                            @elseif ($firstBooking && $editBookingUrl)
                                                x-on:click="window.location = @js(str_replace('__ID__', $firstBooking, $editBookingUrl))"
                                            @endif
                                        >
                                            {{ $day['free'] }}
                                            @if ($room['external'])<span class="ext">⇄</span>@endif
                                            @if (count($day['bookings']))<span class="dot"></span>@endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <template x-if="a !== null">
                    <div class="ta-cal-bar">
                        <div>
                            <div><b x-text="rooms[room].name"></b></div>
                            <div class="muted">
                                <span x-text="fmt(dates[a]) + ' → ' + fmt(dates[last + 1])"></span>
                                · <span x-text="nights"></span> {{ __('nights') }}
                                <template x-if="b === null"><span> · {{ __('click the last night to extend') }}</span></template>
                            </div>
                        </div>
                        <div style="display:flex;gap:.5rem;align-items:center">
                            <template x-if="minFree < 1">
                                <span class="muted" style="color:#fca5a5">{{ __('Not free on all selected nights') }}</span>
                            </template>
                            <x-filament::button color="gray" size="sm" x-on:click="room = null; a = null; b = null">{{ __('Cancel') }}</x-filament::button>
                            <x-filament::button tag="a" icon="heroicon-m-plus" x-bind:href="url()" x-bind:style="minFree < 1 ? 'opacity:.5;pointer-events:none' : ''">
                                {{ __('Quick booking') }}
                            </x-filament::button>
                        </div>
                    </div>
                </template>
            </div>

            <x-filament::section :heading="__('Bookings in this period') . ' (' . $bookings->count() . ')'" collapsible>
                @if ($bookings->isEmpty())
                    <p style="color:rgb(var(--gray-500))">{{ __('No bookings in this period.') }}</p>
                @else
                    <table class="ta-cal-list">
                        <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Room') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($bookings as $b)
                                <tr>
                                    <td>
                                        @if ($editBookingUrl)
                                            <a href="{{ str_replace('__ID__', $b['id'], $editBookingUrl) }}" style="color:rgb(var(--primary-600));font-weight:600">{{ $b['reference'] }}</a>
                                        @else
                                            {{ $b['reference'] }}
                                        @endif
                                    </td>
                                    <td>{{ $b['customer'] }}</td>
                                    <td>{{ $b['room'] }}</td>
                                    <td>{{ $b['from'] ? \Carbon\Carbon::parse($b['from'])->format('d.m.Y') : '—' }}</td>
                                    <td>{{ $b['to'] ? \Carbon\Carbon::parse($b['to'])->format('d.m.Y') : '—' }}</td>
                                    <td>
                                        <x-filament::badge :color="\App\Enums\BookingStatus::tryFrom((string) $b['status'])?->getColor() ?? 'gray'">{{ $statusLabel($b['status']) }}</x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
