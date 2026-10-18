<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center">
        @if (count($agencies = $this->getAgencyOptions()))
            <x-filament::input.wrapper style="min-width:16rem">
                <x-filament::input.select wire:model.live="agencyId">
                    @foreach ($agencies as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @endif
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="locale">
                <option value="mk">Македонски</option><option value="en">English</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::button color="gray" icon="heroicon-m-arrow-path" wire:click="restart">{{ __('New conversation') }}</x-filament::button>
        @unless ($this->aiReady())
            <span style="color:rgb(var(--warning-600));font-size:.85rem">● {{ __('AI is not available for the chat – check AI settings & limits.') }}</span>
        @endunless
    </div>

    <div class="ta-chat-thread" x-data x-effect="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse ($this->messages() as $m)
            <div class="ta-msg ta-msg-{{ $m->role }}" wire:key="p{{ $m->id }}">
                <div class="ta-msg-meta">{{ $m->role === 'visitor' ? __('You (as a customer)') : '🤖 '.__('Bot') }}</div>
                <div class="ta-msg-body">{!! nl2br(e($m->body)) !!}</div>
                @if ($m->items)
                    <div class="ta-msg-items">
                        @foreach ($m->items as $it)
                            <div class="ta-msg-item">@if (! empty($it['image']))<img src="{{ $it['image'] }}" alt="">@endif<div><b>{{ $it['title']['mk'] ?? $it['title']['en'] ?? '' }}</b><small>{{ $it['type'] }}@if (! empty($it['price'])) · {{ __('from') }} {{ $it['price'] }} {{ $it['currency'] }}@endif</small></div></div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p style="color:rgb(var(--gray-500));text-align:center">{{ __('Write a question below, e.g. "Каде да одам на скијање во февруари со деца?"') }}</p>
        @endforelse
        <div wire:loading wire:target="send" class="ta-msg ta-msg-bot"><div class="ta-msg-body">…</div></div>
    </div>

    <form wire:submit="send" class="ta-chat-reply">
        <textarea wire:model="text" rows="2" placeholder="{{ __('Ask like a customer…') }}" x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
        <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled">{{ __('Send') }}</x-filament::button>
    </form>

    <style>
        .ta-chat-thread { display:flex; flex-direction:column; gap:.75rem; min-height:40vh; max-height:62vh; overflow:auto; padding:1rem; border-radius:.75rem; background:rgb(var(--gray-50)); border:1px solid rgb(var(--gray-200)); }
        .dark .ta-chat-thread { background:rgb(var(--gray-900)); border-color:rgb(var(--gray-700)); }
        .ta-msg { max-width:min(640px, 85%); padding:.6rem .85rem; border-radius:1rem; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.06); }
        .dark .ta-msg { background:rgb(var(--gray-800)); }
        .ta-msg-visitor { align-self:flex-end; background:rgb(var(--primary-50)); }
        .dark .ta-msg-visitor { background:rgb(var(--primary-950)); }
        .ta-msg-bot { align-self:flex-start; }
        .ta-msg-meta { font-size:.72rem; color:rgb(var(--gray-500)); margin-bottom:.2rem; }
        .ta-msg-body { font-size:.92rem; line-height:1.45; }
        .ta-msg-items { display:grid; gap:.4rem; margin-top:.5rem; }
        .ta-msg-item { display:flex; gap:.6rem; align-items:center; padding:.35rem; border-radius:.6rem; background:rgb(var(--gray-50)); }
        .dark .ta-msg-item { background:rgba(0,0,0,.25); }
        .ta-msg-item img { width:56px; height:42px; object-fit:cover; border-radius:.4rem; }
        .ta-msg-item b { display:block; font-size:.85rem; } .ta-msg-item small { color:rgb(var(--gray-500)); font-size:.75rem; }
        .ta-chat-reply { display:flex; gap:.75rem; align-items:flex-end; }
        .ta-chat-reply textarea { flex:1; border-radius:.75rem; border:1px solid rgb(var(--gray-300)); padding:.6rem .8rem; background:transparent; }
    </style>
</x-filament-panels::page>
