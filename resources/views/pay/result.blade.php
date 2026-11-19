@php
    $color = $agency->brand_color ?: '#0e7490';
    $logo = $agency->brand_logo ?: $agency->logo;
    $ok = $p->status === 'approved';
    $pending = $p->status === 'pending';
    $ref = $what?->reference;
@endphp
<!doctype html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $ok ? __('Payment successful') : __('Payment not completed') }} – {{ $agency->name }}</title>
<style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #eef2f6; color: #0f172a; font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    .box { background: #fff; border-radius: 16px; padding: 28px 24px; max-width: 460px; width: calc(100% - 32px); text-align: center; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
    .box img { max-height: 48px; max-width: 200px; margin-bottom: 8px; }
    .ico { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; margin: 10px auto; font-size: 34px; color: #fff; background: {{ $ok ? '#16a34a' : ($pending ? '#d97706' : '#dc2626') }}; }
    h1 { font-size: 21px; margin: 6px 0; }
    dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; text-align: left; background: #f8fafc; border-radius: 10px; padding: 12px 14px; margin: 14px 0; font-size: 14px; }
    dt { color: #64748b; } dd { margin: 0; font-weight: 600; }
    .btn { display: block; text-decoration: none; background: {{ $color }}; color: #fff; border: 0; border-radius: 10px; padding: 12px 18px; font: inherit; font-weight: 700; cursor: pointer; width: 100%; margin-top: 10px; box-sizing: border-box; }
    .btn.ghost { background: #fff; color: {{ $color }}; border: 1px solid #e2e8f0; }
    .muted { color: #64748b; font-size: 14px; }
</style>
</head>
<body>
<div class="box">
    @if ($logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $agency->name }}">@else<b>{{ $agency->name }}</b>@endif
    <div class="ico">{{ $ok ? '✓' : ($pending ? '…' : '✕') }}</div>
    <h1>{{ $ok ? __('Payment successful') : ($pending ? __('The payment is not finished') : __('Payment not completed')) }}</h1>
    @if ($ok)
        <p class="muted">{{ __('Thank you! The payment is registered on your booking. A confirmation is on its way to your e-mail.') }}</p>
    @elseif (! $pending)
        <p class="muted">{{ __('The bank did not approve the payment – no money was taken. You can try again with the same or another card, or pay at the agency.') }}</p>
    @endif
    <dl>
        @if ($p->payable_type === 'test')<dt>{{ __('Booking') }}</dt><dd>{{ __('Test payment') }}</dd>@endif
        @if ($ref)<dt>{{ __('Booking') }}</dt><dd>{{ $ref }}</dd>@endif
        <dt>{{ __('Amount') }}</dt><dd>{{ number_format((float) $p->amount, 2, ',', '.') }} {{ $p->currency }}@if ($what && $what->currency && $what->currency !== $p->currency) <span class="muted">({{ number_format((float) $p->booking_amount, 2, ',', '.') }} {{ $what->currency }})</span>@endif</dd>
        @if ($ok && $p->auth_code)<dt>{{ __('Authorization code') }}</dt><dd>{{ $p->auth_code }}</dd>@endif
        @if ($ok && $p->card_mask)<dt>{{ __('Card') }}</dt><dd>{{ $p->card_mask }}</dd>@endif
        <dt>{{ __('Payment number') }}</dt><dd>{{ $p->oid }}</dd>
        @if (! $ok && ! $pending && $p->message)<dt>{{ __('Bank message') }}</dt><dd>{{ $p->message }}</dd>@endif
    </dl>
    @if (! $ok)
        <form method="post" action="{{ route('pay.retry', $p->token) }}">@csrf<button class="btn" type="submit">{{ __('Try again') }}</button></form>
    @endif
    @if ($portal)<a class="btn {{ $ok ? '' : 'ghost' }}" href="{{ $portal }}">{{ __('My booking') }}</a>@endif
    @if ($back)<a class="btn ghost" href="{{ $back }}">{{ $p->payable_type === 'test' ? __('Back to the settings') : __('Back to the website') }}</a>@endif
    <p class="muted" style="margin-top:14px">{{ $agency->name }}@if ($agency->phone) · {{ $agency->phone }}@endif</p>
</div>
</body>
</html>
