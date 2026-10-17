<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TravelObjectResource;
use App\Models\TourOperator;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class OperatorsTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->can('objects.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Tour operators'))
            ->query(TourOperator::query()->withCount([
                'travelObjects',
                'travelObjects as active_count' => fn ($q) => $q->where('is_active', true),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->weight('semibold'),
                Tables\Columns\TextColumn::make('active_count')->label(__('Active'))->badge()->color('success'),
                Tables\Columns\TextColumn::make('travel_objects_count')->label(__('Total'))->badge()->color('gray'),
            ])
            ->recordUrl(fn (TourOperator $r) => TravelObjectResource::getUrl('index', ['tableFilters' => ['tour_operator_id' => ['value' => $r->id]]]))
            ->defaultSort('travel_objects_count', 'desc')
            ->paginated(false);
    }
}
