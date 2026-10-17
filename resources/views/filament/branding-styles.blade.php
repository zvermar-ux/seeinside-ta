@php
    use App\Support\Branding;
    $b = Branding::all();
    $radius = ['none' => '0', 'sm' => '.3rem', 'lg' => '.9rem', 'xl' => '1.25rem'][$b['radius']] ?? null;
    $heading = filled($b['heading_font']) && $b['heading_font'] !== 'system' ? $b['heading_font'] : null;
    $loginBg = Branding::url($b['login_background']);
    $css = (string) ($b['custom_css'] ?? '');
@endphp
<style>
    html { font-size: {{ (int) ($b['font_scale'] ?: 100) }}%; }
    .ta-brand-name { font-weight: 700; font-size: 1.15rem; letter-spacing: -.01em; color: rgb(var(--gray-950)); white-space: nowrap; }
    .dark .ta-brand-name { color: #fff; }
    .fi-header-heading, .fi-simple-header-heading, .fi-section-header-heading, .fi-modal-heading, .fi-ta-header-heading {
        @if ($heading) font-family: '{{ $heading }}', var(--font-family), ui-sans-serif, system-ui, sans-serif; @endif
        font-weight: {{ (int) $b['heading_weight'] ?: 700 }};
    }

    @if ($radius !== null)
        .fi-section, .fi-ta-ctn, .fi-input-wrp, .fi-btn, .fi-icon-btn, .fi-modal-window, .fi-dropdown-panel, .fi-sidebar-item-button,
        .fi-tabs, .fi-tabs-item, .fi-wi-stats-overview-stat, .fi-simple-main, .fi-fo-repeater-item, .fi-fieldset, .fi-pagination-items,
        .fi-ta-filters-dropdown, .fi-dropdown-list-item, .fi-fo-builder-item, .fi-in-entry-wrp, .fi-topbar-item-button, .ta-agency-badge,
        .ta-cal-wrap, .ta-lm-card, .filepond--panel-root { border-radius: {{ $radius }} !important; }
        .fi-badge { border-radius: {{ $b['radius'] === 'none' ? '0' : 'calc('.$radius.' * .6)' }} !important; }
    @endif

    @switch ($b['sidebar_style'])
        @case('tinted')
            .fi-sidebar, .fi-sidebar-header { background: rgba(var(--primary-50), 1) !important; }
            .dark .fi-sidebar, .dark .fi-sidebar-header { background: rgba(var(--primary-950), .45) !important; }
            .fi-sidebar-item-active .fi-sidebar-item-button { background: rgba(var(--primary-500), .14) !important; }
            @break
        @case('dark')
        @case('brand')
            .fi-sidebar, .fi-sidebar-header, .fi-sidebar-nav { background: {{ $b['sidebar_style'] === 'dark' ? 'rgb(var(--gray-900))' : 'rgb(var(--primary-700))' }} !important; }
            .dark .fi-sidebar, .dark .fi-sidebar-header, .dark .fi-sidebar-nav { background: {{ $b['sidebar_style'] === 'dark' ? 'rgb(var(--gray-900))' : 'rgb(var(--primary-900))' }} !important; }
            .fi-sidebar-header { border-color: rgba(255,255,255,.08) !important; box-shadow: none !important; }
            .fi-sidebar .fi-sidebar-item-label, .fi-sidebar .fi-sidebar-group-label, .fi-sidebar .fi-logo, .fi-sidebar .ta-brand-name { color: rgba(255,255,255,.88) !important; }
            .fi-sidebar .fi-sidebar-item-icon, .fi-sidebar .fi-sidebar-group-button .fi-icon-btn, .fi-sidebar .fi-sidebar-header .fi-icon-btn { color: rgba(255,255,255,.55) !important; }
            .fi-sidebar .fi-sidebar-item-button:hover { background: rgba(255,255,255,.08) !important; }
            .fi-sidebar .fi-sidebar-item-active .fi-sidebar-item-button { background: rgba(255,255,255,.14) !important; }
            .fi-sidebar .fi-sidebar-item-active .fi-sidebar-item-label, .fi-sidebar .fi-sidebar-item-active .fi-sidebar-item-icon { color: #fff !important; }
            .fi-sidebar .ta-agency-badge { background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.12); }
            .fi-sidebar .ta-agency-name { color: #fff; }
            .fi-sidebar .ta-agency-role { color: rgba(255,255,255,.6); }
            @break
    @endswitch

    @switch ($b['background'])
        @case('soft')
            .fi-body { background-image: radial-gradient(at 0% 0%, rgba(var(--primary-500), .10) 0, transparent 45%), radial-gradient(at 100% 100%, rgba(var(--info-500), .08) 0, transparent 45%); background-attachment: fixed; }
            @break
        @case('dots')
            .fi-body { background-image: radial-gradient(rgba(var(--gray-400), .28) 1px, transparent 1px); background-size: 18px 18px; }
            .dark .fi-body { background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px); }
            @break
        @case('white')
            html:not(.dark) .fi-body { background-color: #fff; }
            @break
    @endswitch

    @if ($loginBg)
        .fi-simple-layout { background: url('{{ $loginBg }}') center / cover no-repeat fixed; position: relative; }
        @if ($b['login_layout'] === 'split')
            @media (min-width: 1024px) {
                .fi-simple-layout { background-size: 55% 100%; background-position: left center; background-color: rgb(var(--gray-50)); }
                .dark .fi-simple-layout { background-color: rgb(var(--gray-950)); }
                .fi-simple-main-ctn { justify-content: flex-end !important; padding-right: 7vw; }
            }
        @else
            .fi-simple-layout::before { content: ''; position: fixed; inset: 0; background: rgba(15, 23, 42, .35); pointer-events: none; }
            .fi-simple-main-ctn { position: relative; z-index: 1; }
        @endif
        .fi-simple-main { box-shadow: 0 25px 60px rgba(0,0,0,.25) !important; }
    @endif

    .ta-login-intro { text-align: center; margin-bottom: .25rem; }
    .ta-login-intro h2 { font-size: 1.15rem; font-weight: 700; color: rgb(var(--gray-950)); }
    .dark .ta-login-intro h2 { color: #fff; }
    .ta-login-intro p { color: rgb(var(--gray-500)); font-size: .9rem; margin-top: .25rem; white-space: pre-line; }
    .ta-footer { text-align: center; font-size: .78rem; color: rgb(var(--gray-400)); padding: 1.5rem 1rem; white-space: pre-line; }

    {!! str_ireplace('</style', '<\/style', $css) !!}
</style>
