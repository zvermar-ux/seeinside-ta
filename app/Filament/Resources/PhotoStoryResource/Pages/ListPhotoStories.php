<?php

namespace App\Filament\Resources\PhotoStoryResource\Pages;

use App\Filament\Resources\PhotoStoryResource;
use App\Models\PhotoStory;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPhotoStories extends ListRecords
{
    protected static string $resource = PhotoStoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }

    public function getTabs(): array
    {
        $count = fn (string $s) => PhotoStoryResource::getEloquentQuery()->where('status', $s)->count();

        return [
            'pending' => Tab::make(__('Waiting for approval'))->icon('heroicon-o-clock')->badge($count(PhotoStory::PENDING) ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PhotoStory::PENDING)),
            'approved' => Tab::make(__('Approved'))->icon('heroicon-o-check-circle')->modifyQueryUsing(fn (Builder $query) => $query->where('status', PhotoStory::APPROVED)),
            'rejected' => Tab::make(__('Rejected'))->icon('heroicon-o-x-circle')->modifyQueryUsing(fn (Builder $query) => $query->where('status', PhotoStory::REJECTED)),
            'all' => Tab::make(__('All')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return PhotoStoryResource::getEloquentQuery()->where('status', PhotoStory::PENDING)->exists() ? 'pending' : 'approved';
    }
}
