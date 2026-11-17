<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\SupplierDestinationResource\Pages;
use App\Models\SupplierDestination;
use App\Services\Hotels\HotelService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/** Destinations of the booking systems; the active ones are offered in the website hotel search */
class SupplierDestinationResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = SupplierDestination::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static bool $shouldRegisterNavigation = false;

    public static function permissionModule(): string
    {
        return 'accommodations';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Hotel destination'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Hotel destinations (API)');
    }

    public static function table(Table $table): Table
    {
        $flush = fn ($r) => Cache::forget('hotels:dest:'.$r->agency_id);

        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Destination'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (SupplierDestination $r) => collect($r->zones)->pluck('name')->take(6)->implode(', ')),
                Tables\Columns\TextColumn::make('country_name')->label(__('Country'))->sortable()->badge()->color('gray'),
                Tables\Columns\TextColumn::make('code')->label(__('Code'))->copyable(),
                Tables\Columns\TextColumn::make('hotels_count')->label(__('Hotels'))->sortable()->badge(),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('In the website search'))->afterStateUpdated(fn ($record) => $flush($record)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country_code')->label(__('Country'))
                    ->options(fn () => SupplierDestination::query()->distinct()->orderBy('country_name')->pluck('country_name', 'country_code')->all()),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('In the website search')),
            ])
            ->actions([
                Tables\Actions\Action::make('import')->label(__('Import hotels'))->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->form([Forms\Components\Select::make('min_stars')->label(__('Only from'))->default(3)
                        ->options([0 => __('All'), 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★'])])
                    ->action(function (SupplierDestination $record, array $data) {
                        @set_time_limit(0);
                        try {
                            $n = app(HotelService::class)->importHotels($record->connection()->withoutGlobalScopes()->firstOrFail(), $record->code, (int) $data['min_stars']);
                            $record->update(['is_active' => true]);
                            Cache::forget('hotels:dest:'.$record->agency_id);
                            Notification::make()->title(__(':n hotels imported', ['n' => $n]))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('Import stopped'))->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('on')->label(__('Offer in the website search'))->icon('heroicon-o-eye')
                    ->action(function ($records) use ($flush) {
                        $records->each->update(['is_active' => true]);
                        $records->each(fn ($r) => $flush($r));
                    }),
                Tables\Actions\BulkAction::make('off')->label(__('Remove from the website search'))->icon('heroicon-o-eye-slash')
                    ->action(function ($records) use ($flush) {
                        $records->each->update(['is_active' => false]);
                        $records->each(fn ($r) => $flush($r));
                    }),
            ])
            ->emptyStateDescription(__('Booking systems (API) → Hotelbeds → "Import destinations".'));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSupplierDestinations::route('/')];
    }
}
