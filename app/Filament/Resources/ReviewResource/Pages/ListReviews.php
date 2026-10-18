<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->icon('heroicon-o-plus')];
    }

    public function getTabs(): array
    {
        $count = fn (string $s) => ReviewResource::getEloquentQuery()->where('status', $s)->count();

        return [
            'pending' => Tab::make(__('Waiting for approval'))->icon('heroicon-o-clock')->badge($count(Review::PENDING) ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Review::PENDING)),
            'approved' => Tab::make(__('Approved'))->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Review::APPROVED)),
            'rejected' => Tab::make(__('Rejected'))->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Review::REJECTED)),
            'all' => Tab::make(__('All')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return ReviewResource::getEloquentQuery()->where('status', Review::PENDING)->exists() ? 'pending' : 'approved';
    }
}
