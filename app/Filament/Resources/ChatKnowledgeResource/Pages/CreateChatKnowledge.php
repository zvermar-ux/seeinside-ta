<?php

namespace App\Filament\Resources\ChatKnowledgeResource\Pages;

use App\Filament\Resources\ChatKnowledgeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateChatKnowledge extends CreateRecord
{
    protected static string $resource = ChatKnowledgeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['source' => 'manual', 'created_by' => Auth::id()];
    }
}
