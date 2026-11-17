<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\SupplierHotelResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\SupplierConnection;
use App\Models\SupplierDestination;
use App\Models\SupplierHotel;
use App\Services\Hotels\Hotelbeds\HotelbedsDriver;
use App\Services\Hotels\HotelService;
use App\Services\Hotels\HotelText;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/** Hotels from booking systems (Hotelbeds…): descriptions and photos are imported, prices are always live */
class SupplierHotelResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = SupplierHotel::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionModule(): string
    {
        return 'accommodations';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Hotel (API)'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Hotels (API)');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\Placeholder::make('info')->label('')->columnSpanFull()
                    ->content(fn (?SupplierHotel $record) => new HtmlString('<b>'.e($record?->name).'</b> '.str_repeat('★', (int) $record?->stars).' · '.e(trim(($record?->zone_name ? $record->zone_name.', ' : '').$record?->destination_name, ', '))
                        .'<br><span style="opacity:.7">'.e(__('Description, photos and rooms come from the booking system (import again to refresh). Prices are asked live for every search.')).'</span>')),
                Forms\Components\Toggle::make('is_active')->label(__('Show on the website'))->inline(false),
                Forms\Components\Toggle::make('is_featured')->label(__('Recommended (first in the results)'))->inline(false),
                Forms\Components\TextInput::make('markup_percent')->label(__('Own margin %'))->numeric()->suffix('%')
                    ->helperText(__('Empty: the margin of the connection.')),
                Forms\Components\TextInput::make('name')->label(__('Name'))->required()->columnSpan(2),
                Forms\Components\TextInput::make('stars')->label(__('Stars'))->numeric()->minValue(1)->maxValue(5),
                Forms\Components\Textarea::make('description')->label(__('Description'))->rows(6)->columnSpanFull()
                    ->helperText(__('You can translate it to Macedonian; the next import overwrites it.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ranking', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('photo')->label('')->square()->size(48)
                    ->state(fn (SupplierHotel $r) => ! empty($r->images[0]['path']) ? HotelbedsDriver::imageUrl($r->images[0]['path'], 'bigger') : null),
                Tables\Columns\TextColumn::make('name')->label(__('Hotel'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (SupplierHotel $r) => trim(($r->zone_name ? $r->zone_name.', ' : '').$r->destination_name, ', ')),
                Tables\Columns\TextColumn::make('stars')->label(__('Stars'))->sortable()->formatStateUsing(fn ($state) => $state ? str_repeat('★', (int) $state) : '—'),
                Tables\Columns\TextColumn::make('boards')->label(__('Board'))->badge()->color('gray')->limitList(4)
                    ->formatStateUsing(fn ($state) => HotelText::boardGroup($state)),
                Tables\Columns\TextColumn::make('connection.name')->label(__('System'))->badge()->color('info')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('code')->label(__('Code'))->toggleable(isToggledHiddenByDefault: true)->copyable(),
                Tables\Columns\TextColumn::make('markup_percent')->label(__('Margin'))->suffix('%')->placeholder('—')->toggleable(),
                Tables\Columns\ToggleColumn::make('is_featured')->label(__('Recommended')),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('On website')),
                AgencyField::column(),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('destination_code')->label(__('Destination'))->searchable()
                    ->options(fn () => SupplierDestination::query()->where('hotels_count', '>', 0)->orderBy('name')->pluck('name', 'code')->all()),
                Tables\Filters\SelectFilter::make('stars')->label(__('Stars'))->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('On website')),
                Tables\Filters\TernaryFilter::make('is_featured')->label(__('Recommended')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('destinations')->label(__('Destinations'))->icon('heroicon-o-map')->color('gray')
                    ->url(fn () => SupplierDestinationResource::getUrl()),
                Tables\Actions\Action::make('import')->label(__('Import hotels'))->icon('heroicon-o-arrow-down-tray')
                    ->modalDescription(__('Descriptions, photos and rooms of the hotels in the chosen destinations. Takes a while for big destinations (100 hotels per request).'))
                    ->form([
                        Forms\Components\Select::make('connection')->label(__('Booking system'))->required()->live()
                            ->options(fn () => SupplierConnection::query()->where('is_active', true)->whereIn('provider', array_keys(HotelService::DRIVERS))->pluck('name', 'id'))
                            ->default(fn () => SupplierConnection::query()->where('is_active', true)->whereIn('provider', array_keys(HotelService::DRIVERS))->value('id')),
                        Forms\Components\Select::make('destinations')->label(__('Destinations'))->multiple()->required()->searchable()
                            ->options(fn (Forms\Get $get) => SupplierDestination::query()->where('supplier_connection_id', $get('connection'))
                                ->orderByDesc('is_active')->orderBy('country_name')->orderBy('name')->get()
                                ->mapWithKeys(fn ($d) => [$d->code => $d->name.' ('.($d->country_name ?: $d->country_code).') · '.$d->code])->all())
                            ->helperText(__('First import the destinations of the countries in Booking systems (API).')),
                        Forms\Components\Select::make('min_stars')->label(__('Only from'))->default(3)
                            ->options([0 => __('All'), 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']),
                    ])
                    ->action(function (array $data) {
                        @set_time_limit(0);
                        $c = SupplierConnection::query()->findOrFail($data['connection']);
                        $svc = app(HotelService::class);
                        $done = [];
                        try {
                            foreach ($data['destinations'] as $code) {
                                $done[] = $code.': '.$svc->importHotels($c, $code, (int) $data['min_stars']);
                                SupplierDestination::query()->where('supplier_connection_id', $c->id)->where('code', $code)->get()->each->update(['is_active' => true]);
                            }
                            Notification::make()->title(__('Hotels imported'))->body(implode(' · ', $done))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('Import stopped'))->body(($done ? implode(' · ', $done).' — ' : '').$e->getMessage())->danger()->persistent()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('4xl'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('on')->label(__('Show on website'))->icon('heroicon-o-eye')
                    ->action(fn ($records) => $records->each->update(['is_active' => true]))->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('off')->label(__('Hide from website'))->icon('heroicon-o-eye-slash')
                    ->action(fn ($records) => $records->each->update(['is_active' => false]))->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('margin')->label(__('Set margin'))->icon('heroicon-o-receipt-percent')
                    ->form([Forms\Components\TextInput::make('markup_percent')->label(__('Margin %'))->numeric()->helperText(__('Empty: the margin of the connection.'))])
                    ->action(fn ($records, array $data) => $records->each->update(['markup_percent' => $data['markup_percent'] !== '' ? $data['markup_percent'] : null]))->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading(__('No hotels yet'))
            ->emptyStateDescription(__('1) Booking systems (API) → add Hotelbeds with the API key and secret → Test. 2) Import destinations. 3) Import hotels here.'));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSupplierHotels::route('/')];
    }
}
