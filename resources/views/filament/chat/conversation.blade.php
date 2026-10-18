<x-filament-panels::page>
    @php($conv = $this->record)
    <div wire:poll.5s class="ta-chat-thread" x-data x-init="$el.scrollTop = $el.scrollHeight" x-effect="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @foreach ($this->messages() as $m)
            <div class="ta-msg ta-msg-{{ $m->role }}" wire:key="m{{ $m->id }}">
                <div class="ta-msg-meta">
                    @switch($m->role)
                        @case('visitor') {{ $conv->displayName() }} @break
                        @case('bot') 🤖 {{ __('Bot') }} @break
                        @case('operator') 🧑‍💼 {{ $m->user?->name ?? __('Agent') }} @break
                        @default {{ __('System') }}
                    @endswitch
                    · {{ $m->created_at?->format('d.m. H:i') }}
                    @if ($m->rating === 'good') <span title="{{ __('Good answer') }}">👍</span> @elseif ($m->rating === 'bad') <span title="{{ __('Corrected') }}">✏️</span> @endif
                </div>
                <div class="ta-msg-body">{!! nl2br(e($m->body)) !!}</div>
                @if ($m->items)
                    <div class="ta-msg-items">
                        @foreach ($m->items as $it)
                            <div class="ta-msg-item">
                                @if (! empty($it['image']))<img src="{{ $it['image'] }}" alt="">@endif
                                <div><b>{{ $it['title']['mk'] ?? $it['title']['en'] ?? '' }}</b><small>{{ $it['type'] }}@if (! empty($it['price'])) · {{ __('from') }} {{ $it['price'] }} {{ $it['currency'] }}@endif @if (! empty($it['date'])) · {{ \Illuminate\Support\Carbon::parse($it['date'])->format('d.m.Y') }}@endif</small></div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($m->role === 'bot' && $this->canWrite())
                    <div class="ta-msg-train">
                        <button type="button" wire:click="rate({{ $m->id }}, 'good')">👍 {{ __('Good') }}</button>
                        <button type="button" wire:click="mountAction('correct', { id: {{ $m->id }} })">✏️ {{ __('Correct the answer') }}</button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($this->canWrite())
        <form wire:submit="send" class="ta-chat-reply">
            <textarea wire:model="reply" rows="2" placeholder="{{ __('Write an answer to the visitor…') }}" x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
            <x-filament::button type="submit" icon="heroicon-m-paper-airplane">{{ __('Send') }}</x-filament::button>
        </form>
        <p style="font-size:.8rem;color:rgb(var(--gray-500))">{{ $conv->bot_paused ? __('You are answering – the bot is quiet in this conversation.') : __('The bot answers automatically. When you send a message, you take over the conversation.') }}</p>
    @endif

    <style>
        .ta-chat-thread { display:flex; flex-direction:column; gap:.75rem; max-height:62vh; overflow:auto; padding:1rem; border-radius:.75rem; background:rgb(var(--gray-50)); border:1px solid rgb(var(--gray-200)); }
        .dark .ta-chat-thread { background:rgb(var(--gray-900)); border-color:rgb(var(--gray-700)); }
        .ta-msg { max-width:min(640px, 85%); padding:.6rem .85rem; border-radius:1rem; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.06); }
        .dark .ta-msg { background:rgb(var(--gray-800)); }
        .ta-msg-visitor { align-self:flex-start; border-bottom-left-radius:.3rem; }
        .ta-msg-bot, .ta-msg-operator { align-self:flex-end; border-bottom-right-radius:.3rem; background:rgb(var(--primary-50)); }
        .dark .ta-msg-bot, .dark .ta-msg-operator { background:rgb(var(--primary-950)); }
        .ta-msg-operator { background:rgb(var(--warning-50)); }
        .dark .ta-msg-operator { background:rgb(var(--warning-950)); }
        .ta-msg-system { align-self:center; background:transparent; box-shadow:none; font-size:.85rem; color:rgb(var(--gray-500)); }
        .ta-msg-meta { font-size:.72rem; color:rgb(var(--gray-500)); margin-bottom:.2rem; }
        .ta-msg-body { font-size:.92rem; line-height:1.45; }
        .ta-msg-items { display:grid; gap:.4rem; margin-top:.5rem; }
        .ta-msg-item { display:flex; gap:.6rem; align-items:center; padding:.35rem; border-radius:.6rem; background:rgba(255,255,255,.7); }
        .dark .ta-msg-item { background:rgba(0,0,0,.25); }
        .ta-msg-item img { width:56px; height:42px; object-fit:cover; border-radius:.4rem; }
        .ta-msg-item b { display:block; font-size:.85rem; } .ta-msg-item small { color:rgb(var(--gray-500)); font-size:.75rem; }
        .ta-msg-train { display:flex; gap:.5rem; margin-top:.4rem; }
        .ta-msg-train button { font-size:.72rem; padding:.1rem .45rem; border-radius:99px; border:1px solid rgb(var(--gray-300)); color:rgb(var(--gray-600)); }
        .ta-msg-train button:hover { background:rgb(var(--gray-100)); }
        .ta-chat-reply { display:flex; gap:.75rem; align-items:flex-end; }
        .ta-chat-reply textarea { flex:1; border-radius:.75rem; border:1px solid rgb(var(--gray-300)); padding:.6rem .8rem; background:transparent; }
    </style>
</x-filament-panels::page>
