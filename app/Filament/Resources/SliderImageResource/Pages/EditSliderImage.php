<?php

namespace App\Filament\Resources\SliderImageResource\Pages;

use App\Filament\Resources\SliderImageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSliderImage extends EditRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    use EditRecord\Concerns\Translatable;

    protected static string $resource = SliderImageResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->aiActions(), Actions\LocaleSwitcher::make(), Actions\DeleteAction::make()];
    }
}
