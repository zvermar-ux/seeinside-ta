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
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

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
        return mb_strtolower(__('Apartment / villa'));
    }

    public static function getPluralModelLabel(): string
    {
        // own apartments, villas, studios (and hotels the agency enters itself); hotels from booking systems are in "Hotels (API)"
        return __('Apartments & villas');
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
                                ->inline()->required()->default(AccommodationType::Apartment->value)->columnSpanFull(),
                            Forms\Components\Select::make('stars')->label(__('Category'))
                                ->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']),
                            Forms\Components\Select::make('tour_operator_id')->label(__('Tour operator'))
                                ->options(fn (Get $get) => TravelObjectResource::operatorOptions(AgencyField::current($get)))->searchable(),
                            CatalogFields::groupSelect('group_id', GroupType::Group, __('Group')),
                            CatalogFields::groupSelect('destination_id', GroupType::Destination, __('Destination')),
                            CatalogFields::placeSelect(),
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
                            Forms\Components\Section::make(__('Business model'))->icon('heroicon-o-key')->schema([
                                Forms\Components\ToggleButtons::make('ownership')->hiddenLabel()->inline()->live()->default('contract')
                                    ->options(['contract' => __('Contract / allotment'), 'lease' => __('Own annual lease'), 'own' => __('Owned')]),
                                Forms\Components\Grid::make(2)->visible(fn (Get $get) => in_array($get('ownership'), ['lease', 'own'], true))->schema([
                                    Forms\Components\DatePicker::make('lease_from')->label(__('Season / lease from'))->native(false)->displayFormat('d.m.Y'),
                                    Forms\Components\DatePicker::make('lease_to')->label(__('Season / lease to'))->native(false)->displayFormat('d.m.Y')->afterOrEqual('lease_from'),
                                ]),
                                Forms\Components\Placeholder::make('break_even')->hiddenLabel()
                                    ->visible(fn (Get $get, ?\App\Models\Accommodation $record) => $record && in_array($get('ownership'), ['lease', 'own'], true))
                                    ->content(fn (?\App\Models\Accommodation $record) => $record ? new \Illuminate\Support\HtmlString(view('filament.reports.break-even', ['be' => (new \App\Services\Reports\OfferReport($record, \App\Services\Reports\OfferReport::leaseWindow($record)))->breakEven(), 'compact' => true])->render()) : null),
                                Forms\Components\Placeholder::make('lease_help')->hiddenLabel()
                                    ->visible(fn (Get $get) => in_array($get('ownership'), ['lease', 'own'], true))
                                    ->content(__('Enter the lease, maintenance, utilities… in the “Costs” tab below. Every payment from a booking reduces what is left to cover.')),
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
                        Forms\Components\Toggle::make('book_by_terms')->label(__('Booking only in fixed terms (e.g. 15–22.05, 22–29.05…)'))->live()->columnSpan(2)
                            ->helperText(__('Terms and their prices per room are defined in the “Terms” tab below (generator + price matrix).')),
                        Forms\Components\Select::make('term_price_type')->label(__('Term price is'))->selectablePlaceholder(false)->default('per_room')
                            ->options(['per_room' => __('per room / apartment for the whole term'), 'per_person' => __('per person for the whole term')])
                            ->visible(fn (Get $get) => (bool) $get('book_by_terms')),
                        Forms\Components\FileUpload::make('price_list_pdf')->label(__('Price list (PDF)'))->columnSpanFull()
                            ->disk('public')->directory('price-lists')->acceptedFileTypes(['application/pdf'])->downloadable()->openable(),
                        Forms\Components\Placeholder::make('rooms_hint')->hiddenLabel()->columnSpanFull()
                            ->content(__('Rooms and seasonal prices are entered in the "Rooms & price list" tab after saving.')),
                    ]),
                    Forms\Components\Section::make(__('Organized transport'))->columns(4)->collapsible()
                        ->description(__('Bus or plane sold together with the stay – shown on the website as an option and added to the booking as a separate line (also on the invoice).'))
                        ->schema([
                            Forms\Components\Toggle::make('transport.enabled')->label(__('Transport offered'))->live()->inline(false),
                            Forms\Components\Select::make('transport.type')->label(__('Transport'))->options(['bus' => __('Bus'), 'flight' => __('Plane')])->default('bus')
                                ->visible(fn (Get $get) => (bool) $get('transport.enabled')),
                            Forms\Components\TextInput::make('transport.price_adult')->label(__('Price per adult (return)'))->numeric()
                                ->visible(fn (Get $get) => (bool) $get('transport.enabled')),
                            Forms\Components\TextInput::make('transport.price_child')->label(__('Price per child'))->numeric()
                                ->visible(fn (Get $get) => (bool) $get('transport.enabled')),
                            Forms\Components\TextInput::make('transport.free_under')->label(__('Free under (years)'))->numeric()->default(2)
                                ->visible(fn (Get $get) => (bool) $get('transport.enabled')),
                            Forms\Components\TextInput::make('transport.child_age_to')->label(__('Child price up to (years)'))->numeric()->default(12)
                                ->visible(fn (Get $get) => (bool) $get('transport.enabled')),
                            Forms\Components\Select::make('transport.places')->label(__('Departure places'))->multiple()->columnSpan(2)
                                ->options(fn () => \App\Models\DeparturePlace::orderBy('sort_order')->get()->mapWithKeys(fn ($p) => [$p->id => $p->tr('name')]))
                                ->visible(fn (Get $get) => (bool) $get('transport.enabled')),
                            Forms\Components\TextInput::make('transport.note')->label(__('Note (departure time, meeting point…)'))->columnSpanFull()
                                ->visible(fn (Get $get) => (bool) $get('transport.enabled')),
                        ]),
                    Forms\Components\Section::make(__('Source'))->columns(2)->collapsible()->schema([
                        Forms\Components\Select::make('supplier_connection_id')->label(__('Booking system (API)'))
                            ->options(fn (Get $get) => SupplierConnection::query()
                                ->when(AgencyField::current($get), fn ($q, $a) => $q->where('agency_id', $a))->pluck('name', 'id'))
                            ->placeholder(__('Own offer'))->live(),
                        Forms\Components\TextInput::make('provider_code')->label(__('Hotel code in the booking system'))
                            ->visible(fn (Get $get) => ! self::isMcs($get('supplier_connection_id'))),
                        // the old Aries system: choose its apartment; our content stays, prices/terms come from there
                        Forms\Components\Select::make('provider_code')->label(__('Apartment in the old Aries system'))->searchable()
                            ->visible(fn (Get $get) => self::isMcs($get('supplier_connection_id')))
                            ->options(fn (Get $get) => \App\Services\Mcs\McsService::accommodationOptions($get('supplier_connection_id') ? (int) $get('supplier_connection_id') : null))
                            ->helperText(__('Search by name or by the Id of the old system (e.g. 151). Villas in fixed terms are "packages" there. Rooms: in the Rooms tab choose the Room-Id of the old system for each room.')),
                        Forms\Components\Toggle::make('sync_supplier_prices')->label(__('Take term prices from the old system'))->default(true)
                            ->helperText(__('Every night: the fixed terms of the old system and the price of each room for each term (Terms tab). Terms, room prices and period prices entered here are removed at the sync – only those from Aries stay. Texts, photos and rooms stay as they are here.'))
                            ->visible(fn (Get $get) => self::isMcs($get('supplier_connection_id'))),
                        Forms\Components\Toggle::make('manual_prices')->label(__('Prices from our price list (Aries only for terms and free places)'))
                            ->helperText(__('The prices in the Price matrix (typed or pasted with "Paste price list") stay as they are; the sync with the old system only adds terms and marks rooms free / full.'))
                            ->visible(fn (Get $get) => self::isMcs($get('supplier_connection_id'))),
                        Forms\Components\Toggle::make('use_supplier_availability')->label(__('Take availability (free / booked) from the booking system'))
                            ->helperText(__('Each room needs its room code from the booking system (Rooms tab).'))
                            ->visible(fn (Get $get) => ! self::isMcs($get('supplier_connection_id')))
                            ->default(true)->columnSpanFull(),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make(__('Media & promotions'))->icon('heroicon-o-bolt')->schema([
                    CatalogFields::galleries('accommodations'),
                    CatalogFields::videos(),
                    CatalogFields::promotions(),
                    CatalogFields::payment(),
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
                // link to the old Aries system: Id there and how many rooms are linked
                Tables\Columns\TextColumn::make('aries')->label('Aries')->badge()
                    ->visible(fn () => SupplierConnection::query()->where('provider', \App\Enums\SupplierProvider::Mcs->value)->exists())
                    ->state(function (Accommodation $r) {
                        if (! self::isMcs($r->supplier_connection_id) || blank($r->provider_code)) {
                            return __('Not linked');
                        }
                        [, $code] = \App\Services\Mcs\McsService::ref($r->provider_code);
                        $linked = $r->rooms()->whereNotNull('provider_code')->where('provider_code', '!=', '')->count();

                        return 'Id '.$code.' · '.__('rooms').' '.$linked.'/'.$r->rooms_count;
                    })
                    ->color(function ($state, Accommodation $r) {
                        if (! self::isMcs($r->supplier_connection_id) || blank($r->provider_code)) {
                            return 'gray';
                        }
                        preg_match('#(\d+)/(\d+)$#', (string) $state, $m);

                        return ($m && $m[1] === $m[2] && (int) $m[2] > 0) ? 'success' : 'warning';
                    }),
                AgencyField::column()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_featured')->label(__('Featured')),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('type')->label(__('Type'))->options(AccommodationType::class),
                Tables\Filters\TernaryFilter::make('aries')->label(__('Linked to Aries'))
                    ->visible(fn () => SupplierConnection::query()->where('provider', \App\Enums\SupplierProvider::Mcs->value)->exists())
                    ->queries(
                        true: fn ($q) => $q->whereIn('supplier_connection_id', SupplierConnection::query()->where('provider', 'mcs')->select('id'))->whereNotNull('provider_code')->where('provider_code', '!=', ''),
                        false: fn ($q) => $q->where(fn ($w) => $w->whereNotIn('supplier_connection_id', SupplierConnection::query()->where('provider', 'mcs')->select('id'))->orWhereNull('supplier_connection_id')->orWhereNull('provider_code')->orWhere('provider_code', '')),
                    ),
Tables\Filters\SelectFilter::make('place_id')->label(__('Place'))->searchable()
                    ->options(fn () => collect(\App\Filament\Resources\GroupResource::placeOptions(null))->collapse()->all()),
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
                        $newRoom = $to->rooms()->create($room->only(['name', 'room_type', 'board', 'max_adults', 'max_children', 'beds', 'size_m2', 'units', 'description', 'room_amenities', 'cover_image', 'price', 'price_type', 'is_active', 'sort_order']));
                        Duplicate::media($room, $newRoom);
                        foreach ($room->rates as $rate) {
                            $newRoom->rates()->create($rate->only(['date_from', 'date_to', 'price', 'price_type', 'board', 'min_nights', 'release_days', 'is_last_minute', 'note']));
                        }
                    }
                    Duplicate::media($from, $to);
                }, ['supplier_connection_id', 'provider_code']),
                Tables\Actions\Action::make('calendar')->label(__('Availability calendar'))->tooltip(__('Availability calendar'))
                    ->icon('heroicon-o-calendar-days')->color('gray')->iconButton()
                    ->visible(fn () => \App\Filament\Pages\AvailabilityCalendar::canAccess())
                    ->url(fn (Accommodation $record) => \App\Filament\Pages\AvailabilityCalendar::getUrl(['accommodation' => $record->id])),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [RoomsRelationManager::class, AccommodationResource\RelationManagers\StayTermsRelationManager::class, MediaRelationManager::class, \App\Filament\RelationManagers\CostsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccommodations::route('/'),
            'create' => Pages\CreateAccommodation::route('/create'),
            'edit' => Pages\EditAccommodation::route('/{record}/edit'),
            'term-prices' => Pages\TermPrices::route('/{record}/term-prices'),
        ];
    }

    /** The chosen connection is the old Aries system (MCS eTours) */
    public static function isMcs($connectionId): bool
    {
        static $cache = [];
        if (! $connectionId) {
            return false;
        }

        // raw value: value() on the model would give the enum, not the string
        return $cache[$connectionId] ??= SupplierConnection::withoutGlobalScopes()->whereKey($connectionId)->toBase()->value('provider') === \App\Enums\SupplierProvider::Mcs->value;
    }
}
