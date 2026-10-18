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
            Actions\Action::make('test')->label(__('Test the bot'))->icon('heroicon-o-beaker')->color('gray')->url(fn () => ChatPlayground::getUrl()),
            Actions\CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
