<?php

namespace App\Filament\Resources\SliderImageResource\Pages;

use App\Filament\Resources\SliderImageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSliderImage extends CreateRecord
{
    use CreateRecord\Concerns\Translatable;

    protected static string $resource = SliderImageResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\LocaleSwitcher::make()];
    }
}
