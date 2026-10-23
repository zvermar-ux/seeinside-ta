<?php

namespace App\Filament\Resources\AccommodationResource\Pages;

use App\Filament\Resources\AccommodationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccommodation extends EditRecord
{
    use \App\Filament\Concerns\HasAiAssistant;
    protected static string $resource = AccommodationResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->aiActions(), 
            Actions\Action::make('calendar')->label(__('Availability calendar'))->icon('heroicon-o-calendar-days')->color('gray')
                ->visible(fn () => \App\Filament\Pages\AvailabilityCalendar::canAccess())
                ->url(fn () => \App\Filament\Pages\AvailabilityCalendar::getUrl(['accommodation' => $this->record->id])),
            Actions\Action::make('termPrices')->label(__('Price matrix'))->icon('heroicon-o-table-cells')->color('gray')
                ->visible(fn () => (bool) $this->record->book_by_terms)
                ->url(fn () => AccommodationResource::getUrl('term-prices', ['record' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }

    public function getContentTabLabel(): ?string
    {
        return __('Details');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function afterSave(): void
    {
        $this->record->refresh()->save();
    }
}
