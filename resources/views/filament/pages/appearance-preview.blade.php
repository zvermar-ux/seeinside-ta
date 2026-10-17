@php
    $p = $s['primary'] ?? '#0ea5e9';
    $style = $s['sidebar_style'] ?? 'light';
    $side = ['light' => '#ffffff', 'tinted' => 'color-mix(in srgb, '.$p.' 8%, white)', 'dark' => '#111827', 'brand' => 'color-mix(in srgb, '.$p.' 80%, black)'][$style] ?? '#fff';
    $sideText = in_array($style, ['dark', 'brand'], true) ? 'rgba(255,255,255,.85)' : '#334155';
    $r = ['none' => '0', 'sm' => '.3rem', 'md' => '.55rem', 'lg' => '.9rem', 'xl' => '1.25rem'][$s['radius'] ?? 'md'] ?? '.55rem';
    $chip = fn ($c, $t) => '<span style="display:inline-block;padding:.15rem .5rem;border-radius:calc('.$r.' * .6);font-size:.72rem;font-weight:600;color:'.$c.';background:color-mix(in srgb, '.$c.' 14%, transparent)">'.e($t).'</span>';
@endphp
<div style="display:flex;border:1px solid rgba(var(--gray-200),1);border-radius:{{ $r }};overflow:hidden;font-size:.8rem;min-height:12rem">
    <div style="width:46%;min-width:0;white-space:nowrap;overflow:hidden;background:{{ $side }};padding:.75rem;display:flex;flex-direction:column;gap:.35rem;border-right:1px solid rgba(var(--gray-200),1)">
        <div style="font-weight:700;color:{{ in_array($style, ['dark', 'brand'], true) ? '#fff' : '#0f172a' }};margin-bottom:.3rem">{{ ($s['app_name'] ?? null) ?: config('app.name') }}</div>
        <div style="padding:.35rem .5rem;border-radius:{{ $r }};background:color-mix(in srgb, {{ $p }} 16%, transparent);color:{{ in_array($style, ['dark', 'brand'], true) ? '#fff' : $p }};font-weight:600">● {{ __('Tours') }}</div>
        <div style="padding:.35rem .5rem;color:{{ $sideText }}">○ {{ __('Hotels') }}</div>
        <div style="padding:.35rem .5rem;color:{{ $sideText }}">○ {{ __('Bookings') }}</div>
    </div>
    <div style="flex:1;padding:.75rem;display:flex;flex-direction:column;gap:.6rem;background:rgba(var(--gray-50),1)">
        <div style="font-weight:{{ (int) ($s['heading_weight'] ?? 700) }};font-size:1rem;color:rgb(var(--gray-900))">{{ __('Preview') }}</div>
        <span style="align-self:flex-start;padding:.4rem .8rem;border-radius:{{ $r }};background:{{ $p }};color:#fff;font-weight:600">{{ __('Save') }}</span>
        <div style="display:flex;flex-wrap:wrap;gap:.3rem">
            {!! $chip($s['success'] ?? '#10b981', __('Confirmed')) !!}
            {!! $chip($s['warning'] ?? '#f59e0b', __('Pending')) !!}
            {!! $chip($s['danger'] ?? '#f43f5e', __('Cancelled')) !!}
            {!! $chip($s['info'] ?? '#6366f1', __('Inquiry')) !!}
        </div>
        <a style="color:{{ $p }};font-weight:600">{{ __('Link') }} →</a>
    </div>
</div>
