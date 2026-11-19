@php
    $badge = fn ($s) => match ($s) { 'ok' => ['#16a34a', '#dcfce7', __('Linked')], 'part' => ['#b45309', '#fef3c7', __('Partly linked')], default => ['#dc2626', '#fee2e2', __('Not linked')] };
    $dt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y H:i') : '—';
@endphp
<x-filament-panels::page>
    <style>
        .al-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem}
        .al-stat{border:1px solid rgb(var(--gray-200));border-radius:.75rem;padding:.75rem 1rem;background:#fff}
        .dark .al-stat,.dark .al-card{background:rgb(var(--gray-900));border-color:rgb(var(--gray-700))}
        .al-stat b{display:block;font-size:1.4rem;line-height:1.2}
        .al-stat span{font-size:.75rem;color:rgb(var(--gray-500))}
        .al-bar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
        .al-bar input,.al-bar select,.al-card select{border:1px solid rgb(var(--gray-300));border-radius:.5rem;padding:.35rem .6rem;font-size:.85rem;background:transparent;max-width:100%}
        .al-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:1rem}
        @media(max-width:700px){.al-grid{grid-template-columns:1fr}}
        .al-card{border:1px solid rgb(var(--gray-200));border-left:5px solid var(--c);border-radius:.75rem;padding:1rem;background:#fff}
        .al-head{display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start}
        .al-name{font-weight:600}
        .al-name a:hover{text-decoration:underline}
        .al-pill{font-size:.72rem;font-weight:600;padding:.15rem .55rem;border-radius:999px;white-space:nowrap}
        .al-link{display:grid;grid-template-columns:1fr auto 1fr;gap:.5rem;align-items:center;margin:.75rem 0}
        .al-link .al-arrow{color:var(--c);font-weight:700}
        .al-side{font-size:.8rem}
        .al-side small{display:block;color:rgb(var(--gray-500));font-size:.7rem;text-transform:uppercase;letter-spacing:.03em}
        .al-rooms{width:100%;border-collapse:collapse;font-size:.8rem}
        .al-rooms td{border-top:1px solid rgb(var(--gray-100));padding:.35rem .25rem;vertical-align:middle}
        .al-rooms select{width:100%}
        .al-dot{display:inline-block;width:.6rem;height:.6rem;border-radius:50%;margin-right:.3rem}
        .al-meta{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.6rem}
        .al-tag{font-size:.72rem;background:rgb(var(--gray-100));border-radius:.4rem;padding:.15rem .45rem}
        .dark .al-tag{background:rgb(var(--gray-800))}
        .al-tag.aries{background:#e0f2fe;color:#075985}
        .al-muted{color:rgb(var(--gray-500));font-size:.8rem}
    </style>

    <div class="al-stats">
        <div class="al-stat"><b>{{ $stats['accOk'] }} / {{ $stats['acc'] }}</b><span>{{ __('Apartments fully linked') }}</span></div>
        <div class="al-stat"><b>{{ $stats['roomsOk'] }} / {{ $stats['rooms'] }}</b><span>{{ __('Rooms with Aries Room-Id') }}</span></div>
        <div class="al-stat"><b>{{ $stats['terms'] }}</b><span>{{ __('Terms taken from Aries') }}</span></div>
        <div class="al-stat"><b>{{ $stats['prices'] }}</b><span>{{ __('Room prices taken from Aries') }}</span></div>
        <div class="al-stat"><b>{{ $stats['toursOk'] }} / {{ $stats['tours'] }}</b><span>{{ __('Tours linked') }}</span></div>
        <div class="al-stat"><b style="font-size:1rem">{{ $dt($stats['last']) }}</b><span>{{ __('Last sync') }}</span></div>
    </div>

    <div class="al-bar">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by name…') }}">
        <select wire:model.live="show">
            <option value="all">{{ __('All') }}</option>
            <option value="missing">{{ __('Not linked / partly linked') }}</option>
            <option value="linked">{{ __('Linked only') }}</option>
        </select>
        <span class="al-muted">
            <span class="al-dot" style="background:#16a34a"></span>{{ __('Linked') }}
            <span class="al-dot" style="background:#b45309;margin-left:.6rem"></span>{{ __('Partly linked') }}
            <span class="al-dot" style="background:#dc2626;margin-left:.6rem"></span>{{ __('Not linked') }}
        </span>
    </div>

    <x-filament::section :heading="__('Apartments and villas') . ' (' . count($rows) . ')'" collapsible>
        <div class="al-grid">
            @forelse ($rows as $r)
                @php [$c, $bg, $lbl] = $badge($r['status']); @endphp
                <div class="al-card" style="--c:{{ $c }}" wire:key="acc-{{ $r['id'] }}">
                    <div class="al-head">
                        <div>
                            <div class="al-name"><a href="{{ $r['edit'] }}">{{ $r['name'] }}</a></div>
                            @if ($r['place'])<div class="al-muted">{{ $r['place'] }}</div>@endif
                        </div>
                        <span class="al-pill" style="color:{{ $c }};background:{{ $bg }}">{{ $lbl }}</span>
                    </div>

                    <div class="al-link">
                        <div class="al-side"><small>{{ __('New app') }}</small>ID {{ $r['id'] }}</div>
                        <div class="al-arrow">⇄</div>
                        <div class="al-side"><small>{{ __('Old Aries – Id') }}</small>
                            <select wire:change="linkAccommodation({{ $r['id'] }}, $event.target.value)">
                                <option value="">— {{ __('Not linked') }} —</option>
                                @foreach ($options as $k => $v)
                                    <option value="{{ $k }}" @selected($r['ref'] === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if (count($r['rooms']))
                        <table class="al-rooms">
                            @foreach ($r['rooms'] as $room)
                                <tr wire:key="room-{{ $room['id'] }}">
                                    <td style="width:45%"><span class="al-dot" style="background:{{ $room['code'] !== '' ? '#16a34a' : '#dc2626' }}"></span>{{ $room['name'] }} <span class="al-muted">({{ $room['cap'] }})</span></td>
                                    <td>
                                        @if ($r['product'])
                                            <select wire:change="linkRoom({{ $room['id'] }}, $event.target.value)">
                                                <option value="">— {{ __('Not linked') }} —</option>
                                                @foreach ($r['roomOptions'] as $k => $v)
                                                    <option value="{{ $k }}" @selected($room['code'] === (string) $k)>{{ $v }}</option>
                                                @endforeach
                                                @if ($room['code'] !== '' && ! isset($r['roomOptions'][$room['code']]))
                                                    <option value="{{ $room['code'] }}" selected>Room-Id {{ $room['code'] }}</option>
                                                @endif
                                            </select>
                                        @else
                                            <span class="al-muted">{{ __('Link the apartment first') }}</span>
                                        @endif
                                    </td>
                                    <td style="white-space:nowrap;text-align:right" title="{{ __('Room prices from Aries') }}">
                                        @if ($room['prices'])<span class="al-tag aries">{{ $room['prices'] }} {{ __('prices') }}</span>@endif
                                        @if ($room['stop'])<span class="al-tag" style="color:#dc2626">{{ $room['stop'] }} stop</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <p class="al-muted">{{ __('No rooms entered.') }}</p>
                    @endif

                    <div class="al-meta">
                        <span class="al-tag aries">{{ __('Terms from Aries') }}: {{ $r['termsAries'] }} / {{ $r['terms'] }}</span>
                        <span class="al-tag aries">{{ __('Room prices from Aries') }}: {{ $r['pricesAries'] }}</span>
                        @if ($r['stop'])<span class="al-tag" style="color:#dc2626">{{ __('Stop sale') }}: {{ $r['stop'] }}</span>@endif
                        <span class="al-tag">{{ __('Last sync') }}: {{ $dt($r['lastSync']) }}</span>
                        @unless ($r['sync'])<span class="al-tag" style="color:#b45309">{{ __('Price sync off') }}</span>@endunless
                    </div>
                </div>
            @empty
                <p class="al-muted">{{ __('Nothing found.') }}</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('Organized tours') . ' (' . count($tours) . ')'" collapsible collapsed>
        <div class="al-grid">
            @forelse ($tours as $t)
                @php [$c, $bg, $lbl] = $badge($t['status']); @endphp
                <div class="al-card" style="--c:{{ $c }}" wire:key="tour-{{ $t['id'] }}">
                    <div class="al-head">
                        <div class="al-name"><a href="{{ $t['edit'] }}">{{ $t['name'] }}</a></div>
                        <span class="al-pill" style="color:{{ $c }};background:{{ $bg }}">{{ $lbl }}</span>
                    </div>
                    <div class="al-link">
                        <div class="al-side"><small>{{ __('New app') }}</small>ID {{ $t['id'] }}</div>
                        <div class="al-arrow">⇄</div>
                        <div class="al-side"><small>{{ __('Old Aries – package Id') }}</small>
                            <select wire:change="linkTour({{ $t['id'] }}, $event.target.value)">
                                <option value="">— {{ __('Not linked') }} —</option>
                                @foreach ($packageOptions as $k => $v)
                                    <option value="{{ $k }}" @selected($t['code'] === (string) $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                            @if (count($t['roomOptions']) > 1)
                                <select style="margin-top:.35rem" wire:change="linkTourRoom({{ $t['id'] }}, $event.target.value)">
                                    <option value="">— {{ __('Accommodation (Room-Id)') }} —</option>
                                    @foreach ($t['roomOptions'] as $k => $v)
                                        <option value="{{ $k }}" @selected($t['room'] === (string) $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>
                    <div class="al-meta">
                        <span class="al-tag aries">{{ __('Departures from Aries') }}: {{ $t['depsAries'] }} / {{ $t['deps'] }}</span>
                        <span class="al-tag">{{ __('Last sync') }}: {{ $dt($t['lastSync']) }}</span>
                    </div>
                </div>
            @empty
                <p class="al-muted">{{ __('Nothing found.') }}</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
