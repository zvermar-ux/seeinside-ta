<?php

namespace App\Filament\Resources\ChatQuestionnaireResource\Pages;

use App\Filament\Resources\ChatQuestionnaireResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChatQuestionnaire extends EditRecord
{
    protected static string $resource = ChatQuestionnaireResource::class;

    /** options repeater keeps only the MK text: turn it into {mk, en} pairs the website expects */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['questions'] = array_values(array_map(function ($q) {
            $q['options'] = array_values(array_filter(array_map(fn ($o) => is_array($o) ? ['mk' => $o['mk'] ?? '', 'en' => $o['en'] ?? null] : ['mk' => (string) $o, 'en' => null], (array) ($q['options'] ?? [])), fn ($o) => $o['mk'] !== ''));

            return $q;
        }, (array) ($data['questions'] ?? [])));

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
