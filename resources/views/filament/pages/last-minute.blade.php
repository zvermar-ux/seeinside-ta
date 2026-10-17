<x-filament-panels::page>
    <p style="color:rgb(var(--gray-500));margin-top:-1rem">
        {{ __('Everything marked as Last minute (with a valid date). Mark an offer as Last minute in its "Promotions" section or on a single departure date.') }}
    </p>

    @foreach ($sections as $label => $items)
        <x-filament::section :heading="$label . ' (' . count($items) . ')'" collapsible>
            @if (count($items))
                <div class="ta-lm-grid">
                    @foreach ($items as $item)
                        <a href="{{ $item['url'] }}" class="ta-lm-card">
                            <div class="ta-lm-img" @if($item['image']) style="background-image:url('{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['image']) }}')" @endif>
                                <span class="ta-lm-badge">LAST MINUTE</span>
                            </div>
                            <div class="ta-lm-body">
                                <div class="ta-lm-title">{{ $item['title'] }}</div>
                                @if ($item['meta'])<div class="ta-lm-meta">{{ $item['meta'] }}</div>@endif
                                <div class="ta-lm-foot">
                                    <span class="ta-lm-price">{{ $item['price'] ?? '—' }}</span>
                                    @if ($item['until'])<span class="ta-lm-meta">{{ __('until') }} {{ $item['until'] }}</span>@endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <p style="color:rgb(var(--gray-500))">{{ __('Nothing marked as Last minute.') }}</p>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
