@php($r = $getRecord())
<div class="sta-lib-card">
    <span class="sta-lib-card-img"><img src="{{ $r->url() }}" alt="{{ $r->alt }}" loading="lazy"></span>
    <b title="{{ $r->name }}">{{ $r->name }}</b>
    <small>{{ trim(($r->width ? $r->width.'×'.$r->height.' · ' : '').$r->sizeLabel()) }}@if($r->folder) · {{ $r->folder }}@endif</small>
    @if(auth()->user()?->isSuperAdmin())<small>{{ $r->agency?->name ?? __('Shared') }}</small>@endif
</div>
