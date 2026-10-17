<?php

namespace App\Filament\Resources\ApiClientResource\Pages;

use App\Filament\Resources\ApiClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageApiClients extends ManageRecords
{
    protected static string $resource = ApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('plugin')->label(__('WordPress plugin'))->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn () => is_file(base_path('wordpress/dist/seeinside-travel.zip')))
                ->action(fn () => response()->download(base_path('wordpress/dist/seeinside-travel.zip'), 'seeinside-travel.zip')),
            Actions\CreateAction::make()->icon('heroicon-o-plus')->after(fn (\App\Models\ApiClient $record) => ApiClientResource::showKey($record)),
        ];
    }
}
