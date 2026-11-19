@php
    $color = $agency->brand_color ?: '#0e7490';
    $logo = $agency->brand_logo ?: $agency->logo;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('Card payment') }} – {{ $agency->name }}</title>
<style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #eef2f6; color: #0f172a; font: 15px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    .box { background: #fff; border-radius: 16px; padding: 28px 24px; max-width: 420px; width: calc(100% - 32px); text-align: center; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
    .box img { max-height: 48px; max-width: 200px; margin-bottom: 12px; }
    .spin { width: 34px; height: 34px; margin: 14px auto; border: 3px solid #e2e8f0; border-top-color: {{ $color }}; border-radius: 50%; animation: s 1s linear infinite; }
    @keyframes s { to { transform: rotate(360deg); } }
    .amt { font-size: 22px; font-weight: 800; }
    button { margin-top: 12px; background: {{ $color }}; color: #fff; border: 0; border-radius: 10px; padding: 12px 18px; font: inherit; font-weight: 700; cursor: pointer; width: 100%; }
    small { color: #64748b; display: block; margin-top: 10px; }
</style>
</head>
<body>
<div class="box">
    @if ($logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $agency->name }}">@else<b>{{ $agency->name }}</b>@endif
    <div>{{ __('Card payment') }}</div>
    <div class="amt">{{ number_format((float) $p->amount, 2, ',', '.') }} {{ $p->currency }}</div>
    <div class="spin" aria-hidden="true"></div>
    <div>{{ __('Opening the secure payment page of Halk Bank…') }}</div>
    <form id="pay" method="post" action="{{ $form['url'] }}">
        @foreach ($form['fields'] as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <noscript><button type="submit">{{ __('Continue to payment') }}</button></noscript>
    </form>
    <small>🔒 {{ __('The card details are entered only on the page of the bank.') }}</small>
</div>
<script>setTimeout(function () { document.getElementById('pay').submit(); }, 300);</script>
</body>
</html>
