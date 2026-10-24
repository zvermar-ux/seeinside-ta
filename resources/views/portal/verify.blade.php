@php
    use App\Enums\BookingStatus;
    $color = $agency->brand_color ?: '#0e7490';
    $logo = $agency->brand_logo ?: $agency->logo;
    $from = $b->date_from ?? $b->departure?->start_date;
    $to = $b->date_to ?? $b->departure?->end_date;
    $pax = $b->passengerList();
    $paid = $b->total > 0 && $b->balance() <= 0;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>{{ $b->reference }} – {{ $agency->name }}</title>
<style>
    body { margin: 0; background: #eef2f6; color: #0f172a; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    .w { max-width: 520px; margin: 0 auto; padding: 18px 14px 40px; }
    .card { background: #fff; border-radius: 18px; border: 1px solid #e2e8f0; overflow: hidden; }
    .top { padding: 14px 18px; border-bottom: 4px solid {{ $color }}; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .top img { max-height: 40px; max-width: 180px; } .top b { color: {{ $color }}; font-size: 18px; }
    .state { padding: 22px 18px; text-align: center; }
    .state .ic { width: 64px; height: 64px; border-radius: 50%; display: inline-grid; place-items: center; font-size: 34px; color: #fff; }
    .ok .ic { background: #16a34a; } .bad .ic { background: #dc2626; } .wait .ic { background: #d97706; }
    .state h1 { margin: 10px 0 2px; font-size: 22px; } .state p { margin: 0; color: #64748b; }
    .ref { font-size: 26px; font-weight: 800; letter-spacing: .06em; margin-top: 6px; }
    dl { margin: 0; padding: 4px 18px 16px; display: grid; grid-template-columns: auto 1fr; gap: 8px 14px; }
    dt { color: #64748b; font-size: 14px; } dd { margin: 0; font-weight: 600; }
    ol { margin: 0; padding-left: 20px; } li { font-weight: 600; }
    .pay { margin: 0 18px 18px; padding: 10px 12px; border-radius: 10px; font-weight: 700; text-align: center; }
    .pay.y { background: #dcfce7; color: #166534; } .pay.n { background: #fef3c7; color: #92400e; }
    .admin { display: block; margin: 0 18px 18px; text-align: center; background: {{ $color }}; color: #fff; padding: 12px; border-radius: 10px; text-decoration: none; font-weight: 700; }
    .foot { text-align: center; color: #64748b; font-size: 13px; margin-top: 14px; }
</style>
</head>
<body><div class="w">
<div class="card">
    <div class="top">@if ($logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $agency->name }}">@else<b>{{ $agency->name }}</b>@endif<span style="color:#64748b;font-size:13px">{{ __('Booking check') }}</span></div>
    <div class="state {{ $b->status === BookingStatus::Cancelled ? 'bad' : ($valid ? 'ok' : 'wait') }}">
        <span class="ic">{{ $b->status === BookingStatus::Cancelled ? '✕' : ($valid ? '✓' : '…') }}</span>
        <h1>{{ $b->status === BookingStatus::Cancelled ? __('Booking cancelled') : ($valid ? __('Valid booking') : __('Not confirmed yet')) }}</h1>
        <p>{{ $b->status?->getLabel() }}</p>
        <div class="ref">{{ $b->reference }}</div>
    </div>
    <dl>
        <dt>{{ __('Offer') }}</dt><dd>{{ $b->itemTitle() }}</dd>
        @if ($from)<dt>{{ __('Dates') }}</dt><dd>{{ $from->format('d.m.Y') }}@if ($to) – {{ $to->format('d.m.Y') }}@endif @if ($b->nights()) ({{ $b->nights() }} {{ __('nights') }})@endif</dd>@endif
        @if ($b->room)<dt>{{ __('Room / unit') }}</dt><dd>{{ $b->room->tr('name') }}@if ($b->room->board) · {{ $b->room->board->value }}@endif @if ($b->room_number) · {{ $b->room_number }}@endif</dd>@endif
        <dt>{{ __('Guests') }}</dt><dd>{{ (int) $b->adults }} {{ __('adults') }}@if ($b->children) + {{ (int) $b->children }} {{ __('children') }}@endif</dd>
        <dt>{{ __('Holder') }}</dt><dd>{{ mb_strtoupper((string) $b->customer_name) }}</dd>
        @if (count($pax) > 1 || ($pax && trim(($pax[0]['first_name'] ?? '').' '.($pax[0]['last_name'] ?? '')) !== $b->customer_name))
            <dt>{{ __('Passengers') }}</dt><dd><ol>@foreach ($pax as $p)<li>{{ mb_strtoupper(trim(($p['last_name'] ?? '').' '.($p['first_name'] ?? ''))) }}</li>@endforeach</ol></dd>
        @endif
    </dl>
    @if ($b->total > 0 && $b->status !== BookingStatus::Cancelled)
        <div class="pay {{ $paid ? 'y' : 'n' }}">{{ $paid ? '✓ '.__('Paid in full') : __('Not fully paid yet') }}</div>
    @endif
    @if ($staff)
        <a class="admin" href="{{ url('/admin/bookings/'.$b->id.'/edit') }}">{{ __('Open in the admin') }} →</a>
    @endif
</div>
<p class="foot">{{ $agency->name }}@if ($agency->phone) · {{ $agency->phone }}@endif</p>
</div></body></html>
