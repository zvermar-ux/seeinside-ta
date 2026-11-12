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
use Illuminate\Support\Facades\Auth;

class ListChatKnowledge extends ListRecords
{
    protected static string $resource = ChatKnowledgeResource::class;

    public function getSubheading(): ?string
    {
        return __('The bot answers from this knowledge and from the active offer (tours, events, hotels, visas – always up to date). Corrected answers have priority.');
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
            Actions\Action::make('learn')->label(__('Learn from conversations'))->icon('heroicon-o-academic-cap')->color('success')
                ->modalHeading(__('Learn from conversations'))
                ->modalDescription(__('AI reads the recent chats (badly rated answers, chats handed to an agent and the agents\' answers first), finds wrong, vague or missing answers and writes suggestions. They are saved switched off: check them, fill in [ДОПОЛНИ: …] and turn them on.'))
                ->modalSubmitActionLabel(__('Analyse the chats'))
                ->form(fn () => array_filter([
                    Auth::user()->isSuperAdmin() ? Forms\Components\Select::make('agency_id')->label(__('Agency'))->required()->options(Agency::query()->orderBy('name')->pluck('name', 'id')) : null,
                    Forms\Components\Select::make('days')->label(__('Period'))->default(14)->required()
                        ->options([7 => __('Last 7 days'), 14 => __('Last 14 days'), 30 => __('Last 30 days'), 90 => __('Last 90 days')]),
                ]))
                ->action(function (array $data) {
                    try {
                        $n = ChatTrainer::review((int) ($data['agency_id'] ?? Auth::user()->agency_id), (int) ($data['days'] ?? 14));
                        Notification::make()->success()->title(__('Done'))->body(__(':count suggestions written. Filter by source "Suggested from chats", check them and turn them on.', ['count' => $n]))->persistent()->send();
                    } catch (AiException $e) {
                        Notification::make()->danger()->title(__('AI'))->body($e->getMessage())->persistent()->send();
                    }
                }),
            Actions\Action::make('test')->label(__('Test the bot'))->icon('heroicon-o-beaker')->color('gray')->url(fn () => ChatPlayground::getUrl()),
            Actions\CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
