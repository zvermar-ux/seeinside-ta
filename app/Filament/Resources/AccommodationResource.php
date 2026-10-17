<?php

namespace App\Filament\Resources;

use App\Enums\AccommodationType;
use App\Enums\Board;
use App\Enums\GroupType;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\RelationManagers\MediaRelationManager;
use App\Filament\Resources\AccommodationResource\Pages;
use App\Filament\Resources\AccommodationResource\RelationManagers\RoomsRelationManager;
use App\Filament\Support\AgencyField;
use App\Filament\Support\CatalogFields;
use App\Filament\Support\Lang;
use App\Models\Accommodation;
use App\Models\Amenity;
use App\Models\SupplierConnection;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccommodationResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = Accommodation::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionModule(): string
    {
        return 'accommodations';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Hotel / apartment'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Hotels & apartments');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'place', 'country'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('accommodation')->persistTabInQueryString()->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make(__('Overview'))->icon('heroicon-o-information-circle')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Section::make()->columnSpan(2)->columns(2)->schema([
                            AgencyField::select(),
                            Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->columnSpanFull(),
                            Forms\Components\ToggleButtons::make('type')->label(__('Type'))->options(AccommodationType::class)
                                ->inline()->required()->default(AccommodationType::Hotel->value)->columnSpanFull(),
                            Forms\Components\Select::make('stars')->label(__('Category'))
                                ->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']),
                            Forms\Components\Select::make('tour_operator_id')->label(__('Tour operator'))
                                ->options(fn (Get $get) => TravelObjectResource::operatorOptions(AgencyField::current($get)))->searchable(),
                            CatalogFields::groupSelect('group_id', GroupType::Group, __('Group')),
                            CatalogFields::groupSelect('destination_id', GroupType::Destination, __('Destination')),
                            Lang::tabs(fn (string $l) => [
                                Lang::area('short_description', __('Short description'), $l, 2),
                                Lang::editor('overview', __('Overview (general information)'), $l),
                            ]),
                        ]),
                        Forms\Components\Group::make()->columnSpan(1)->schema([
                            CatalogFields::photos('accommodations'),
                            Forms\Components\Section::make(__('Stay'))->columns(2)->schema([
                                Forms\Components\TextInput::make('check_in_time')->label(__('Check-in'))->placeholder('14:00'),
                                Forms\Components\TextInput::make('check_out_time')->label(__('Check-out'))->placeholder('10:00'),
                                Forms\Components\CheckboxList::make('board_types')->label(__('Board'))->options(Board::class)->columns(2)->columnSpanFull(),
                            ]),
                            CatalogFields::publishing(),
                        ]),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make(__('Location'))->icon('heroicon-o-map-pin')->schema([
                    Forms\Components\Section::make()->columns(4)->schema([
                        Forms\Components\TextInput::make('address')->label(__('Address'))->columnSpan(2),
                        Forms\Components\TextInput::make('place')->label(__('Place')),
                        Forms\Components\TextInput::make('region')->label(__('Region')),
                        Forms\Components\TextInput::make('country')->label(__('Country')),
                        Forms\Components\TextInput::make('distance_beach_m')->label(__('To the beach (m)'))->numeric(),
                        Forms\Components\TextInput::make('distance_center_m')->label(__('To the center (m)'))->numeric(),
                        Forms\Components\TextInput::make('distance_ski_lift_m')->label(__('To the ski lift (m)'))->numeric(),
                        Forms\Components\TextInput::make('distance_airport_km')->label(__('To the airport (km)'))->numeric(),
                        ...CatalogFields::location(),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make(__('Amenities'))->icon('heroicon-o-sparkles')->schema([
                    Forms\Components\CheckboxList::make('amenities')->hiddenLabel()->relationship('amenities', 'key')
                        ->getOptionLabelFromRecordUsing(fn (Amenity $a) => $a->tr('name'))
                        ->columns(4)->searchable()->bulkToggleable(),
                ]),
                Forms\Components\Tabs\Tab::make(__('House rules & notes'))->icon('heroicon-o-clipboard-document-list')->schema([
                    Lang::tabs(fn (string $l) => [
                        Lang::editor('house_rules', __('House rules'), $l),
                        Lang::editor('notes', __('Note'), $l),
                        Lang::area('cancellation_policy', __('Cancellation policy'), $l),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make(__('Prices & booking'))->icon('heroicon-o-currency-euro')->schema([
                    Forms\Components\Section::make()->columns(3)->schema([
                        Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR'),
                        Forms\Components\Select::make('booking_mode')->label(__('Booking mode'))->selectablePlaceholder(false)
                            ->options(['request' => __('On request (agency confirms)'), 'instant' => __('Instant confirmation')])->default('request'),
                        Forms\Components\Placeholder::make('price_from_info')->label(__('Price from (website)'))
                            ->content(fn (?Accommodation $record) => $record?->price_from ? number_format((float) $record->price_from, 2, ',', '.').' '.$record->currency : '—'),
                        Forms\Components\FileUpload::make('price_list_pdf')->label(__('Price list (PDF)'))->columnSpanFull()
                            ->disk('public')->directory('price-lists')->acceptedFileTypes(['application/pdf'])->downloadable()->openable(),
                        Forms\Components\Placeholder::make('rooms_hint')->hiddenLabel()->columnSpanFull()
                            ->content(__('Rooms and seasonal prices are entered in the "Rooms & price list" tab after saving.')),
                    ]),
                    Forms\Components\Section::make(__('Source'))->columns(2)->collapsible()->schema([
                        Forms\Components\Select::make('supplier_connection_id')->label(__('Booking system (API)'))
                            ->options(fn (Get $get) => SupplierConnection::query()
                                ->when(AgencyField::current($get), fn ($q, $a) => $q->where('agency_id', $a))->pluck('name', 'id'))
                            ->placeholder(__('Own offer')),
                        Forms\Components\TextInput::make('provider_code')->label(__('Hotel code in the booking system')),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make(__('Media & promotions'))->icon('heroicon-o-bolt')->schema([
                    CatalogFields::videos(),
                    CatalogFields::promotions(),
                    CatalogFields::seo(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('destination')->withCount('rooms'))
            ->columns([
                CatalogFields::coverColumn(),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->weight('semibold')->wrap()
                    ->description(fn (Accommodation $r) => collect([$r->place, $r->country])->filter()->implode(' · '))
                    ->extraAttributes(['style' => 'min-width:14rem']),
                Tables\Columns\TextColumn::make('type')->label(__('Type'))->badge(),
                Tables\Columns\TextColumn::make('stars')->label(__('Cat.'))->formatStateUsing(fn ($state) => $state ? str_repeat('★', (int) $state) : '')->color('warning'),
                Tables\Columns\TextColumn::make('destination.name')->label(__('Destination'))->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('rooms_count')->label(__('Rooms'))->badge()->color('gray'),
                Tables\Columns\TextColumn::make('price_from')->label(__('Price from'))->sortable()
                    ->formatStateUsing(fn ($state, Accommodation $r) => $state ? number_format((float) $state, 0, ',', '.').' '.$r->currency : '—'),
                CatalogFields::lastMinuteColumn(),
                AgencyField::column()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_featured')->label(__('Featured')),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('type')->label(__('Type'))->options(AccommodationType::class),
                Tables\Filters\SelectFilter::make('destination_id')->label(__('Destination'))
                    ->options(fn () => GroupResource::optionsFor(GroupType::Destination, null))->searchable(),
                Tables\Filters\SelectFilter::make('stars')->label(__('Category'))->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']),
                CatalogFields::lastMinuteFilter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Duplicate::action(function (Accommodation $from, Accommodation $to) {
                    $to->amenities()->sync($from->amenities()->pluck('amenities.id'));
                    foreach ($from->rooms()->with('rates')->get() as $room) {
                        $newRoom = $to->rooms()->create($room->only(['name', 'room_type', 'board', 'max_adults', 'max_children', 'beds', 'size_m2', 'units', 'description', 'room_amenities', 'price', 'price_type', 'is_active', 'sort_order']));
                        foreach ($room->rates as $rate) {
                            $newRoom->rates()->create($rate->only(['date_from', 'date_to', 'price', 'price_type', 'board', 'min_nights', 'release_days', 'is_last_minute', 'note']));
                        }
                    }
                    Duplicate::media($from, $to);
                }, ['supplier_connection_id', 'provider_code']),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [RoomsRelationManager::class, MediaRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccommodations::route('/'),
            'create' => Pages\CreateAccommodation::route('/create'),
            'edit' => Pages\EditAccommodation::route('/{record}/edit'),
        ];
    }
}
