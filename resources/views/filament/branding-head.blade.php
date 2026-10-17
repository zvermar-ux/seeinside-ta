@php
    use App\Support\Branding;
    $icon = Branding::url(Branding::get('app_icon')) ?? Branding::url(Branding::get('favicon'));
@endphp
<meta name="theme-color" content="{{ Branding::primaryHex() }}">
<meta name="application-name" content="{{ Branding::appName() }}">
<meta name="apple-mobile-web-app-title" content="{{ Branding::appName() }}">
<meta name="apple-mobile-web-app-capable" content="yes">
@if ($icon)
    <link rel="apple-touch-icon" href="{{ $icon }}">
@endif
<link rel="manifest" href="{{ route('app.manifest') }}">
