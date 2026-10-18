<?php

namespace App\Filament\Resources\ChatQuestionnaireResource\Pages;

use App\Filament\Resources\ChatQuestionnaireResource;
use App\Models\Agency;
use App\Models\ChatQuestionnaire;
use App\Services\Ai\AiException;
use App\Services\Ai\ChatTrainer;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListChatQuestionnaires extends ListRecords
{
    protected static string $resource = ChatQuestionnaireResource::class;

    public function getSubheading(): ?string
    {
        return __('In the live chat the visitor answers a few questions and gets recommendations – only from your active tours, events, hotels and visas.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('ai')->label(__('Create with AI'))->icon('heroicon-o-sparkles')
                ->modalHeading(__('Questionnaire made by AI'))
                ->modalDescription(__('Describe the topic. AI writes the questions and answers (MK and EN) based on your offer. The questionnaire is saved as inactive so you can check it first.'))
                ->form([
                    Forms\Components\Select::make('agency_id')->label(__('Agency'))->required()->visible(fn () => Auth::user()->isSuperAdmin())
                        ->options(Agency::query()->orderBy('name')->pluck('name', 'id')),
                    Forms\Components\TextInput::make('brief')->label(__('Topic'))->required()->maxLength(200)
                        ->placeholder('Каде на зимување 2027 – за семејства со деца'),
                    Forms\Components\Select::make('category')->label(__('Category'))->options(ChatQuestionnaire::categories()),
                    Forms\Components\Select::make('count')->label(__('Number of questions'))->options([3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7])->default(5),
                ])
                ->action(function (array $data) {
                    try {
                        $q = ChatTrainer::questionnaire((int) ($data['agency_id'] ?? Auth::user()->agency_id), $data['brief'], $data['category'] ?? null, (int) ($data['count'] ?? 5));
                        Notification::make()->success()->title(__('Questionnaire created'))->body(__('Check it and turn it on.'))->send();
                        $this->redirect(ChatQuestionnaireResource::getUrl('edit', ['record' => $q]));
                    } catch (AiException $e) {
                        Notification::make()->danger()->title(__('AI'))->body($e->getMessage())->persistent()->send();
                    }
                }),
            Actions\CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
