<?php

namespace App\Filament\Resources\ChatKnowledgeResource\Pages;

use App\Filament\Pages\ChatPlayground;
use App\Filament\Resources\ChatKnowledgeResource;
use App\Models\Agency;
use App\Services\Ai\AiException;
use App\Services\Ai\ChatTrainer;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ListChatKnowledge extends ListRecords
{
    protected static string $resource = ChatKnowledgeResource::class;

    public function getSubheading(): ?string
    {
        $text = __('The bot answers from this knowledge and from the active offer (tours, events, hotels, visas – always up to date). Corrected answers have priority.');
        $st = $this->faqStatus();

        return match ($st['state'] ?? null) {
            'queued', 'running' => $text.' ⏳ '.__('Questions & answers are being written…'),
            'done' => $text.' ✓ '.__(':count questions & answers written. Check them and test the bot.', ['count' => $st['count'] ?? 0]),
            'error' => $text.' ⚠ '.__('Questions & answers').': '.($st['message'] ?? ''),
            default => $text,
        };
    }

    protected function faqStatus(): array
    {
        $agencyId = Auth::user()->agency_id;
        if (! $agencyId) {   // super admin: the last started one
            $agencyId = Cache::get('chat-faq:last');
        }

        return $agencyId ? (array) Cache::get('chat-faq:status:'.$agencyId, []) : [];
    }

    public function table(Table $table): Table
    {
        // refresh while the questions & answers are being written
        return parent::table($table)->poll(fn () => in_array($this->faqStatus()['state'] ?? null, ['queued', 'running'], true) ? '10s' : null);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analyze')->label(__('Analyse website & offer'))->icon('heroicon-o-sparkles')
                ->modalHeading(__('Analyse website & offer'))
                ->modalDescription(__('AI reads your website (home, contact, terms, payment, FAQ pages), destinations and the whole active offer, and writes the knowledge for the bot. Earlier analysis entries are replaced; your own entries stay.'))
                ->modalSubmitActionLabel(__('Analyse'))
                ->form(fn () => Auth::user()->isSuperAdmin() ? [
                    Forms\Components\Select::make('agency_id')->label(__('Agency'))->required()->options(Agency::query()->orderBy('name')->pluck('name', 'id')),
                ] : [])
                ->action(function (array $data) {
                    try {
                        $n = ChatTrainer::analyze((int) ($data['agency_id'] ?? Auth::user()->agency_id));
                        Notification::make()->success()->title(__('Done'))->body(__(':count knowledge entries written. Check them and test the bot.', ['count' => $n]))->send();
                    } catch (AiException $e) {
                        Notification::make()->danger()->title(__('AI'))->body($e->getMessage())->persistent()->send();
                    }
                }),
            Actions\Action::make('faq')->label(__('Questions & answers (AI)'))->icon('heroicon-o-chat-bubble-left-right')->color('info')
                ->modalHeading(__('Questions & answers for the bot'))
                ->modalDescription(__('AI reads the whole website (up to 20 pages from the menu, general terms), the active offer and the knowledge, and writes 40–70 questions visitors really ask with answers. Takes 1–3 minutes. Earlier AI questions & answers are replaced; your entries stay.'))
                ->modalSubmitActionLabel(__('Write questions & answers'))
                ->form(fn () => Auth::user()->isSuperAdmin() ? [
                    Forms\Components\Select::make('agency_id')->label(__('Agency'))->required()->options(Agency::query()->orderBy('name')->pluck('name', 'id')),
                ] : [])
                ->action(function (array $data) {
                    $agencyId = (int) ($data['agency_id'] ?? Auth::user()->agency_id);
                    // long AI work: done in the background by the scheduler (chat:faq --requested, every minute)
                    $q = (array) Cache::get('chat-faq:queue', []);
                    Cache::put('chat-faq:queue', array_values(array_unique([...$q, $agencyId])), 3600);
                    Cache::put('chat-faq:last', $agencyId, 86400);
                    Cache::put('chat-faq:status:'.$agencyId, ['state' => 'queued', 'user' => Auth::id(), 'at' => now()->toIso8601String()], 3600);
                    Notification::make()->success()->title(__('Started'))->body(__('The questions & answers are written in the background (2–5 minutes). This page shows when they are ready.'))->send();
                }),
            Actions\Action::make('test')->label(__('Test the bot'))->icon('heroicon-o-beaker')->color('gray')->url(fn () => ChatPlayground::getUrl()),
            Actions\CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
