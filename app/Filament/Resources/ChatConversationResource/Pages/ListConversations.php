<?php

namespace App\Filament\Resources\ChatConversationResource\Pages;

use App\Filament\Pages\ChatPlayground;
use App\Filament\Pages\LiveChatSettings;
use App\Filament\Resources\ChatConversationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConversations extends ListRecords
{
    protected static string $resource = ChatConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test')->label(__('Test the bot'))->icon('heroicon-o-beaker')->color('gray')
                ->url(fn () => ChatPlayground::getUrl())->visible(fn () => ChatPlayground::canAccess()),
            Actions\Action::make('settings')->label(__('Live chat settings'))->icon('heroicon-o-cog-6-tooth')->color('gray')
                ->url(fn () => LiveChatSettings::getUrl())->visible(fn () => LiveChatSettings::canAccess()),
        ];
    }
}
