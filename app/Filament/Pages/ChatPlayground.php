<?php

namespace App\Filament\Pages;

use App\Models\Agency;
use App\Models\ChatConversation;
use App\Services\Ai\Ai;
use App\Services\Ai\ChatBot;
use App\Services\Ai\ChatConfig;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Operators chat with the bot exactly as a visitor would (not shown in Conversations) */
class ChatPlayground extends Page
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'ai';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'test-bot';

    protected static string $view = 'filament.chat.playground';

    public ?int $agencyId = null;

    public ?int $conversationId = null;

    public string $text = '';

    public string $locale = 'mk';

    public static function defaultNavigationLabel(): string
    {
        return __('Test the bot');
    }

    public function getTitle(): string
    {
        return __('Test the bot');
    }

    public function getSubheading(): ?string
    {
        return __('Ask like a customer would. Wrong answer? Add or correct the knowledge in Bot knowledge and ask again. Costs are counted to the live chat limit.');
    }

    public static function canAccess(): bool
    {
        $u = Auth::user();

        return $u && ($u->isSuperAdmin() || ($u->agency_id && $u->can('live_chat.manage')));
    }

    public function getAgencyOptions(): array
    {
        return Auth::user()->isSuperAdmin() ? Agency::query()->orderBy('name')->pluck('name', 'id')->all() : [];
    }

    public function mount(): void
    {
        $this->agencyId = Auth::user()->isSuperAdmin() ? Agency::query()->orderBy('id')->value('id') : (int) Auth::user()->agency_id;
    }

    public function updatedAgencyId(): void
    {
        if (! Auth::user()->isSuperAdmin()) {
            $this->agencyId = (int) Auth::user()->agency_id;
        }
        $this->conversationId = null;
    }

    public function updatedLocale(): void
    {
        $this->conversationId = null;
    }

    public function aiReady(): bool
    {
        return Ai::available($this->agencyId, 'chat');
    }

    protected function conversation(): ChatConversation
    {
        $c = $this->conversationId ? ChatConversation::withoutGlobalScopes()->where('agency_id', $this->agencyId)->find($this->conversationId) : null;
        if (! $c) {
            $c = ChatConversation::create([
                'agency_id' => $this->agencyId, 'visitor_id' => (string) Str::uuid(), 'token' => Str::random(48), 'channel' => 'test',
                'name' => Auth::user()->name.' (test)', 'locale' => in_array($this->locale, ['mk', 'en'], true) ? $this->locale : 'mk',
            ]);
            $g = ChatConfig::get($this->agencyId)['greeting'];
            $c->messages()->create(['role' => 'bot', 'body' => $g[$c->locale] ?? $g['mk'] ?? '']);
            $this->conversationId = $c->id;
        }

        return $c;
    }

    public function messages()
    {
        return $this->conversationId ? $this->conversation()->messages()->get() : collect();
    }

    public function send(): void
    {
        $text = trim($this->text);
        if ($text === '' || ! $this->agencyId) {
            return;
        }
        $c = $this->conversation();
        $c->messages()->create(['role' => 'visitor', 'body' => $text]);
        $c->increment('messages_count');
        $this->text = '';
        ChatBot::reply($c->fresh());
    }

    public function restart(): void
    {
        if ($this->conversationId) {
            ChatConversation::withoutGlobalScopes()->where('channel', 'test')->whereKey($this->conversationId)->delete();
        }
        $this->conversationId = null;
    }
}
