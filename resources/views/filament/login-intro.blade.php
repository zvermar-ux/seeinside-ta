@php
    $title = \App\Support\Branding::text('login_title');
    $text = \App\Support\Branding::text('login_text');
@endphp
@if ($title || $text)
    <div class="ta-login-intro">
        @if ($title)<h2>{{ $title }}</h2>@endif
        @if ($text)<p>{{ $text }}</p>@endif
    </div>
@endif
