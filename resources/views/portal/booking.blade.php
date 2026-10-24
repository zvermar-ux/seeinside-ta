@php
    use App\Enums\BookingStatus;
    $color = $agency->brand_color ?: '#0e7490';
    $logo = $agency->brand_logo ?: $agency->logo;
    $cur = $b->currency ?: 'EUR';
    $m = fn ($v, $c = null) => number_format((float) $v, 2, ',', '.').' '.($c ?? $cur);
    $q = $lang !== ($b->locale === 'en' ? 'en' : 'mk') ? ['lang' => $lang] : [];
    $from = $b->date_from ?? $b->departure?->start_date;
    $to = $b->date_to ?? $b->departure?->end_date;
    $status = $b->status;
    $steps = [
        ['key' => 'request', 'label' => __('Request'), 'done' => true],
        ['key' => 'confirmed', 'label' => __('Confirmed'), 'done' => in_array($status, [BookingStatus::Confirmed, BookingStatus::Completed], true)],
        ['key' => 'paid', 'label' => __('Paid'), 'done' => $b->total > 0 && $b->balance() <= 0],
        ['key' => 'trip', 'label' => __('Trip'), 'done' => $status === BookingStatus::Completed || ($to && $to->isPast())],
    ];
    $pct = $b->total > 0 ? min(100, round((float) $b->paid_amount / (float) $b->total * 100)) : 0;
    $pax = $b->passengerList();
    $paxRows = max(count($pax), (int) $b->adults + (int) $b->children, 1);
    $img = $b->bookable?->cover_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($b->bookable->cover_image) : null;
@endphp
<!doctype html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('My booking') }} {{ $b->reference }} – {{ $agency->name }}</title>
<style>
    :root { --c: {{ $color }}; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --soft: #f8fafc; --ok: #16a34a; --bad: #dc2626; --warn: #d97706; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #eef2f6; color: var(--ink); font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    a { color: var(--c); }
    .top { background: #fff; border-bottom: 1px solid var(--line); }
    .top .in { max-width: 980px; margin: 0 auto; padding: 12px 16px; display: flex; align-items: center; gap: 12px; justify-content: space-between; }
    .top img { max-height: 44px; max-width: 200px; display: block; }
    .top .brand { font-weight: 800; font-size: 19px; color: var(--c); text-decoration: none; }
    .top .act { display: flex; gap: 8px; align-items: center; font-size: 14px; flex-wrap: wrap; justify-content: flex-end; }
    .pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; border: 1px solid var(--line); background: #fff; text-decoration: none; color: var(--ink); font-weight: 600; }
    .wrap { max-width: 980px; margin: 0 auto; padding: 18px 16px 60px; display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr) 320px; align-items: start; }
    @media (max-width: 860px) { .wrap { grid-template-columns: 1fr; } }
    .card { background: #fff; border-radius: 16px; border: 1px solid var(--line); overflow: hidden; }
    .card .pad { padding: 18px 20px; }
    .card h2 { margin: 0 0 12px; font-size: 18px; }
    .hero { position: relative; }
    .hero .ph { height: 180px; background: var(--soft) center/cover no-repeat; }
    .hero h1 { margin: 0; font-size: 22px; line-height: 1.25; }
    .ref { font-size: 13px; color: var(--muted); }
    .ref b { color: var(--ink); font-size: 15px; letter-spacing: .03em; }
    .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
    .b-inquiry, .b-pending { background: #fef3c7; color: #92400e; } .b-confirmed, .b-completed { background: #dcfce7; color: #166534; } .b-cancelled { background: #fee2e2; color: #991b1b; }
    .steps { display: flex; margin: 16px 0 4px; }
    .steps div { flex: 1; text-align: center; font-size: 12px; color: var(--muted); position: relative; padding-top: 26px; }
    .steps div::before { content: ''; position: absolute; top: 6px; left: 50%; transform: translateX(-50%); width: 16px; height: 16px; border-radius: 50%; background: #fff; border: 3px solid var(--line); z-index: 1; }
    .steps div::after { content: ''; position: absolute; top: 12px; left: -50%; width: 100%; height: 3px; background: var(--line); }
    .steps div:first-child::after { display: none; }
    .steps .done { color: var(--ink); font-weight: 700; } .steps .done::before { background: var(--c); border-color: var(--c); } .steps .done::after { background: var(--c); }
    .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 14px; }
    .facts div { background: var(--soft); border-radius: 12px; padding: 10px 12px; }
    .facts small { display: block; color: var(--muted); font-size: 12px; }
    .facts b { font-size: 15px; }
    .money .row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--line); }
    .money .row:last-of-type { border: 0; }
    .money .big { font-size: 22px; font-weight: 800; color: var(--c); }
    .bar { height: 10px; border-radius: 999px; background: var(--soft); overflow: hidden; margin: 10px 0 4px; border: 1px solid var(--line); }
    .bar span { display: block; height: 100%; background: var(--ok); }
    .due { margin-top: 10px; padding: 10px 12px; border-radius: 10px; background: #fff7ed; color: #9a3412; font-weight: 600; font-size: 14px; }
    .bank { margin-top: 10px; font-size: 13px; color: var(--muted); }
    .docs a { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 11px 12px; border: 1px solid var(--line); border-radius: 12px; text-decoration: none; color: var(--ink); margin-bottom: 8px; font-weight: 600; }
    .docs a:hover { border-color: var(--c); }
    .docs a span { color: var(--c); font-size: 13px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 10px; padding: 11px 18px; background: var(--c); color: #fff; font-weight: 700; font-size: 15px; cursor: pointer; text-decoration: none; }
    .btn.ghost { background: #fff; color: var(--c); box-shadow: inset 0 0 0 1.5px var(--c); }
    .btn.danger { background: #fff; color: var(--bad); box-shadow: inset 0 0 0 1.5px #fecaca; }
    .btn.block { width: 100%; }
    textarea, input, select { width: 100%; font: inherit; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; color: var(--ink); }
    textarea:focus, input:focus { outline: 2px solid var(--c); outline-offset: 0; border-color: transparent; }
    label { display: grid; gap: 4px; font-size: 13px; font-weight: 600; color: #334155; }
    .grid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    .thread { display: grid; gap: 10px; margin-bottom: 14px; max-height: 520px; overflow: auto; padding: 4px; }
    .msg { max-width: 85%; padding: 10px 14px; border-radius: 14px; white-space: pre-wrap; word-wrap: break-word; font-size: 14px; }
    .msg small { display: block; font-size: 11px; opacity: .7; margin-top: 4px; white-space: normal; }
    .msg.out { background: var(--soft); border: 1px solid var(--line); justify-self: start; border-bottom-left-radius: 4px; }
    .msg.in { background: var(--c); color: #fff; justify-self: end; border-bottom-right-radius: 4px; }
    .msg.note { background: #fff; border: 1px dashed var(--line); color: var(--muted); justify-self: center; font-size: 13px; }
    .flash { padding: 12px 14px; border-radius: 12px; background: #dcfce7; color: #166534; font-weight: 600; grid-column: 1 / -1; }
    .errors { padding: 12px 14px; border-radius: 12px; background: #fee2e2; color: #991b1b; grid-column: 1 / -1; }
    .pax { border: 1px solid var(--line); border-radius: 12px; padding: 12px; margin-bottom: 10px; }
    .pax h4 { margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: var(--c); }
    details summary { cursor: pointer; font-weight: 700; color: var(--c); }
    .stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
    .stars input { display: none; } .stars label { font-size: 30px; color: #cbd5e1; cursor: pointer; line-height: 1; }
    .stars input:checked ~ label, .stars label:hover, .stars label:hover ~ label { color: #f59e0b; }
    .contact a { display: flex; gap: 8px; align-items: center; padding: 8px 0; text-decoration: none; font-weight: 600; }
    .muted { color: var(--muted); font-size: 13px; }
    .side { display: grid; gap: 16px; }
    @media (min-width: 861px) { .side { position: sticky; top: 16px; } }
    /* phone: price & documents right after the booking summary */
    @media (max-width: 860px) { .maincol { display: contents !important; } .hero { order: 1; } .side { order: 2; } .maincol > section:not(.hero) { order: 3; } }
</style>
</head>
<body>
<header class="top"><div class="in">
    @if ($logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $agency->name }}">@else<span class="brand">{{ $agency->name }}</span>@endif
    <div class="act">
        @if ($agency->phone)<a class="pill" href="tel:{{ preg_replace('/[^0-9+]/', '', $agency->phone) }}">📞 {{ $agency->phone }}</a>@endif
        <a class="pill" href="{{ route('portal.show', [$token, 'lang' => $lang === 'mk' ? 'en' : 'mk']) }}">{{ $lang === 'mk' ? 'EN' : 'МК' }}</a>
    </div>
</div></header>

<main class="wrap">
    @if (session('ok'))<div class="flash">✔ {{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="errors">{{ implode(' ', $errors->all()) }}</div>@endif

    <div class="maincol" style="display:grid;gap:16px;min-width:0">
        <section class="card hero">
            @if ($img)<div class="ph" style="background-image:url('{{ $img }}')"></div>@endif
            <div class="pad">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap">
                    <div>
                        <div class="ref">{{ __('Booking number') }} <b>{{ $b->reference }}</b></div>
                        <h1>{{ $b->itemTitle() }}</h1>
                    </div>
                    <span class="badge b-{{ $status?->value }}">{{ $status?->getLabel() }}</span>
                </div>
                @if ($b->cancel_requested_at && $status !== BookingStatus::Cancelled)
                    <div class="due" style="margin-top:12px">{{ __('Cancellation requested on :date – waiting for the agency.', ['date' => $b->cancel_requested_at->format('d.m.Y')]) }}</div>
                @endif
                @if ($status !== BookingStatus::Cancelled)
                    <div class="steps">@foreach ($steps as $s)<div class="{{ $s['done'] ? 'done' : '' }}">{{ $s['label'] }}</div>@endforeach</div>
                @endif
                <div class="facts">
                    @if ($from)<div><small>{{ __('Check-in / departure') }}</small><b>{{ $from->translatedFormat('D d.m.Y') }}</b></div>@endif
                    @if ($to)<div><small>{{ __('Check-out / return') }}</small><b>{{ $to->translatedFormat('D d.m.Y') }}</b></div>@endif
                    @if ($b->nights())<div><small>{{ __('Nights') }}</small><b>{{ $b->nights() }}</b></div>@endif
                    <div><small>{{ __('Guests') }}</small><b>{{ (int) $b->adults }} {{ __('adults') }}@if($b->children) + {{ (int) $b->children }} {{ __('children') }}@endif</b></div>
                    @if ($b->room)<div><small>{{ __('Room / unit') }}</small><b>{{ $b->room->tr('name', $lang) }}@if($b->room->board) · {{ $b->room->board->value }}@endif</b></div>@endif
                    @if ($b->departurePlace)<div><small>{{ __('Departure place') }}</small><b>{{ $b->departurePlace->tr('name', $lang) }}</b></div>@endif
                </div>
                @if ($b->contract_note)<p class="muted" style="margin:12px 0 0">{{ $b->contract_note }}</p>@endif
            </div>
        </section>

        <section class="card" id="passengers"><div class="pad">
            <h2>{{ __('Passengers') }}</h2>
            @if ($canEditPax)
                <details @if(! $pax || collect($pax)->contains(fn ($p) => empty($p['birth_date']))) open @endif>
                    <summary>{{ $pax ? __('Check / complete passenger details') : __('Enter passenger details') }}</summary>
                    <form method="post" action="{{ route('portal.passengers', [$token] + $q) }}" style="margin-top:12px">@csrf
                        @for ($k = 0; $k < $paxRows; $k++)
                            @php $p = $pax[$k] ?? []; @endphp
                            <div class="pax"><h4>{{ __('Passenger') }} {{ $k + 1 }}</h4>
                                <div class="grid">
                                    <label>{{ __('First name') }} *<input name="passengers[{{ $k }}][first_name]" value="{{ old("passengers.$k.first_name", $p['first_name'] ?? '') }}" required></label>
                                    <label>{{ __('Last name') }} *<input name="passengers[{{ $k }}][last_name]" value="{{ old("passengers.$k.last_name", $p['last_name'] ?? '') }}" required></label>
                                    <label>{{ __('Date of birth') }} *<input type="date" name="passengers[{{ $k }}][birth_date]" value="{{ old("passengers.$k.birth_date", ! empty($p['birth_date']) ? \Carbon\Carbon::parse($p['birth_date'])->toDateString() : '') }}" max="{{ now()->toDateString() }}" required></label>
                                    <label>{{ __('Passport no.') }}<input name="passengers[{{ $k }}][passport]" value="{{ old("passengers.$k.passport", $p['passport'] ?? '') }}"></label>
                                    <label>{{ __('Phone') }}<input type="tel" name="passengers[{{ $k }}][phone]" value="{{ old("passengers.$k.phone", $p['phone'] ?? '') }}"></label>
                                </div>
                            </div>
                        @endfor
                        <button class="btn" type="submit">{{ __('Save passengers') }}</button>
                    </form>
                </details>
            @endif
            @if ($pax)
                <div style="margin-top:12px">
                    @foreach ($pax as $k => $p)
                        <div style="padding:8px 0;border-top:1px solid var(--line)"><b>{{ $k + 1 }}. {{ mb_strtoupper(trim(($p['last_name'] ?? '').' '.($p['first_name'] ?? ''))) }}</b>
                            <span class="muted">@if(! empty($p['birth_date'])) · {{ \Carbon\Carbon::parse($p['birth_date'])->format('d.m.Y') }}@endif @if(! empty($p['passport'])) · {{ __('passport') }} {{ $p['passport'] }}@endif</span></div>
                    @endforeach
                </div>
            @endif
        </div></section>

        <section class="card" id="messages"><div class="pad">
            <h2>{{ __('Messages with :agency', ['agency' => $agency->name]) }}</h2>
            <div class="thread">
                @forelse ($messages as $msg)
                    <div class="msg {{ $msg->direction === 'in' ? 'in' : ($msg->event && $msg->channel !== 'portal' ? 'note' : 'out') }}">@if($msg->event && $msg->channel !== 'portal' && $msg->direction === 'out')<b>{{ $msg->subject }}</b>@else{{ $msg->body }}@endif<small>{{ $msg->direction === 'in' ? __('You') : $agency->name }} · {{ $msg->created_at->format('d.m.Y H:i') }}@if($msg->event && $msg->channel !== 'portal') · {{ $msg->channel === 'email' ? 'e-mail' : ucfirst($msg->channel) }}@endif</small></div>
                @empty
                    <p class="muted">{{ __('Questions, special wishes (room, bed, transfer…)? Write to us here – we answer as soon as possible.') }}</p>
                @endforelse
            </div>
            <form method="post" action="{{ route('portal.message', [$token] + $q) }}">@csrf
                <textarea name="body" rows="3" required minlength="2" maxlength="3000" placeholder="{{ __('Write a message to the agency…') }}"></textarea>
                <div style="margin-top:8px"><button class="btn" type="submit">{{ __('Send message') }}</button></div>
            </form>
        </div></section>

        @if ($canReview)
            <section class="card"><div class="pad">
                <h2>{{ __('How was your trip?') }}</h2>
                <form method="post" action="{{ route('portal.review', [$token] + $q) }}" style="display:grid;gap:10px">@csrf
                    <div class="stars">@for ($s = 5; $s >= 1; $s--)<input type="radio" name="rating" id="r{{ $s }}" value="{{ $s }}" required><label for="r{{ $s }}">★</label>@endfor</div>
                    <label>{{ __('Title') }}<input name="title" maxlength="200"></label>
                    <label>{{ __('Your experience') }} *<textarea name="body" rows="4" required minlength="10"></textarea></label>
                    <div><button class="btn" type="submit">{{ __('Send review') }}</button></div>
                </form>
            </div></section>
        @endif
    </div>

    <aside class="side">
        <section class="card money"><div class="pad">
            <h2>{{ __('Price & payments') }}</h2>
            @if ($b->total !== null)
                <div class="row"><span>{{ __('Total') }}</span><b>{{ $m($b->total) }}</b></div>
                <div class="row"><span>{{ __('Paid') }}</span><b style="color:var(--ok)">{{ $m($b->paid_amount) }}</b></div>
                <div class="bar"><span style="width:{{ $pct }}%"></span></div>
                <div class="row" style="border:0"><span>{{ __('Balance') }}</span><span class="big">{{ $m($b->balance()) }}</span></div>
                @if ($b->balance() > 0 && $b->payment_due)
                    <div class="due">⏰ {{ __('Please pay by :date', ['date' => $b->payment_due->format('d.m.Y')]) }}</div>
                @endif
                @if ($b->balance() > 0 && $b->option_until && $status !== BookingStatus::Confirmed)
                    <div class="due">{{ __('Reservation held until :date', ['date' => $b->option_until->format('d.m.Y')]) }}</div>
                @endif
                @if ($b->balance() > 0 && $agency->doc('bank_account'))
                    <div class="bank">{{ __('Bank transfer') }}: <b>{{ $agency->doc('bank_account') }}</b> {{ $agency->doc('bank_name') }}<br>{{ __('Reference') }}: <b>{{ $b->reference }}</b></div>
                @endif
                @if ($b->payments->isNotEmpty())
                    <details style="margin-top:10px"><summary>{{ __('Payments') }} ({{ $b->payments->count() }})</summary>
                        @foreach ($b->payments as $p)<div class="row"><span>{{ $p->paid_at?->format('d.m.Y') }}</span><span>{{ $m($p->amount) }}</span></div>@endforeach
                    </details>
                @endif
            @else
                <p class="muted">{{ __('The agency will send you the price shortly.') }}</p>
            @endif
        </div></section>

        <section class="card docs"><div class="pad">
            <h2>{{ __('Documents') }}</h2>
            <a href="{{ route('portal.doc', [$token, 'contract'] + $q) }}" target="_blank" rel="noopener">📄 {{ __('Travel contract') }} <span>{{ __('Open') }} ↗</span></a>
            @if (in_array($status, [BookingStatus::Confirmed, BookingStatus::Completed], true))
                <a href="{{ route('portal.doc', [$token, 'voucher'] + $q) }}" target="_blank" rel="noopener">🎫 {{ __('Voucher') }} <span>{{ __('Open') }} ↗</span></a>
            @endif
            @foreach ($invoices as $inv)
                <a href="{{ route('portal.doc', [$token, 'invoice', $inv->id] + $q) }}" target="_blank" rel="noopener">🧾 {{ $inv->title() }} <span>{{ $m($inv->total, $inv->currency) }}</span></a>
            @endforeach
            <p class="muted" style="margin:6px 0 0">{{ __('Open a document and choose “Print / Save as PDF”.') }}</p>
        </div></section>

        <section class="card contact"><div class="pad">
            <h2>{{ __('Contact') }}</h2>
            @if ($agency->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $agency->phone) }}">📞 {{ $agency->phone }}</a>@endif
            @if ($wa = \App\Services\Messaging\Channels::phone($agency->msg('agency_phone', $agency->phone), $agency))
                <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($b->reference.' – ') }}" target="_blank" rel="noopener">💬 WhatsApp</a>
                <a href="viber://chat?number=%2B{{ $wa }}">💜 Viber</a>
            @endif
            @if ($agency->email)<a href="mailto:{{ $agency->email }}?subject={{ rawurlencode($b->reference) }}">✉️ {{ $agency->email }}</a>@endif
            @if ($agency->address)<p class="muted" style="margin:6px 0 0">{{ $agency->address }}@if($agency->city), {{ $agency->city }}@endif</p>@endif
        </div></section>

        @if ($canCancel)
            <section class="card"><div class="pad">
                <details><summary style="color:var(--bad)">{{ __('Cancel the booking') }}</summary>
                    <form method="post" action="{{ route('portal.cancel', [$token] + $q) }}" style="margin-top:10px;display:grid;gap:8px" onsubmit="return confirm(@js(__('Send a cancellation request?')))">@csrf
                        <p class="muted" style="margin:0">{{ __('The agency confirms the cancellation and informs you about any cancellation costs according to the general terms.') }}</p>
                        <textarea name="reason" rows="2" maxlength="1000" placeholder="{{ __('Reason (optional)') }}"></textarea>
                        <button class="btn danger" type="submit">{{ __('Request cancellation') }}</button>
                    </form>
                </details>
            </div></section>
        @endif
    </aside>
</main>
</body>
</html>
