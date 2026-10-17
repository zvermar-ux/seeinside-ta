<?php

namespace App\Filament\Resources;

use App\Enums\Board;
use App\Enums\DepartureStatus;
use App\Enums\GroupType;
use App\Enums\TourType;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\RelationManagers\MediaRelationManager;
use App\Filament\Resources\TourResource\Pages;
use App\Filament\Support\AgencyField;
use App\Filament\Support\CatalogFields;
use App\Filament\Support\Lang;
use App\Models\Airline;
use App\Models\DeparturePlace;
use App\Models\Tour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TourResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = Tour::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 1;

    public static function permissionModule(): string
    {
        return 'tours';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Organized tour'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Organized tours');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return (string) $record?->tr('title');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'location', 'country'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('tour')->persistTabInQueryString()->columnSpanFull()->tabs([

                Forms\Components\Tabs\Tab::make(__('Basic information'))->icon('heroicon-o-information-circle')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Section::make()->columnSpan(2)->columns(2)->schema([
                            AgencyField::select(),
                            Lang::tabs(fn (string $l) => [
                                Lang::text('title', __('Title'), $l, true),
                                Lang::text('subtitle', __('Subtitle / route'), $l),
                                Lang::area('short_description', __('Short description'), $l, 2),
                            ]),
                            Forms\Components\Select::make('tour_type')->label(__('Tour type'))
                                ->options(TourType::class)->default(TourType::Bus->value)->required(),
                            Forms\Components\Select::make('tour_operator_id')->label(__('Tour operator'))
                                ->options(fn (Get $get) => TravelObjectResource::operatorOptions(AgencyField::current($get)))->searchable(),
                            CatalogFields::groupSelect('group_id', GroupType::Group, __('Group')),
                            CatalogFields::groupSelect('destination_id', GroupType::Destination, __('Destination')),
                            Forms\Components\TextInput::make('location')->label(__('Location / route'))->placeholder('Неапол, Соренто, Амалфи'),
                            Forms\Components\TextInput::make('country')->label(__('Country')),
                            ...CatalogFields::location(),
                        ]),
                        Forms\Components\Group::make()->columnSpan(1)->schema([
                            Forms\Components\Section::make(__('Tour details'))->columns(2)->schema([
                                Forms\Components\TextInput::make('duration_days')->label(__('Days'))->numeric()->minValue(1),
                                Forms\Components\TextInput::make('duration_nights')->label(__('Nights'))->numeric()->minValue(0),
                                Forms\Components\TextInput::make('group_size_min')->label(__('Group min.'))->numeric(),
                                Forms\Components\TextInput::make('group_size_max')->label(__('Group max.'))->numeric(),
                                Forms\Components\Toggle::make('aries_quality')->label(__('Aries quality standard'))->columnSpanFull(),
                                Forms\Components\Toggle::make('has_guide')->label(__('Tour leader / guide'))->columnSpanFull(),
                                Forms\Components\Toggle::make('is_flight')->label(__('Flight tour'))->live()->columnSpanFull(),
                                Forms\Components\Select::make('airline_id')->label(__('Airline'))->columnSpanFull()
                                    ->options(fn () => Airline::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()->visible(fn (Get $get) => $get('is_flight')),
                            ]),
                            CatalogFields::publishing(),
                        ]),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make(__('Description'))->icon('heroicon-o-document-text')->schema([
                    Lang::tabs(fn (string $l) => [
                        Lang::editor('description', __('Description'), $l),
                        Lang::lines('highlights', __('Highlights'), $l),
                        Lang::editor('agenda', __('Agenda'), $l),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make(__('Program by days'))->icon('heroicon-o-calendar-days')
                    ->badge(fn (?Tour $record) => $record?->days()->count() ?: null)
                    ->schema([
                        Forms\Components\Repeater::make('days')->hiddenLabel()->relationship()
                            ->orderColumn('sort_order')->collapsible()->cloneable()
                            ->itemLabel(fn (array $state) => __('Day').' '.($state['day_number'] ?? '').(filled($state['title']['mk'] ?? null) ? ' — '.$state['title']['mk'] : ''))
                            ->addActionLabel(__('Add day'))
                            ->columns(4)
                            ->schema([
                                Forms\Components\TextInput::make('day_number')->label(__('Day'))->numeric()->required()
                                    ->default(fn (Get $get) => count((array) $get('../../days')) ?: 1),
                                Forms\Components\TextInput::make('meals')->label(__('Meals'))->placeholder('B, L, D'),
                                Forms\Components\TextInput::make('overnight')->label(__('Overnight'))->columnSpan(2),
                                Lang::tabs(fn (string $l) => [
                                    Lang::text('title', __('Title'), $l),
                                    Lang::editor('description', __('Description'), $l),
                                ]),
                            ]),
                    ]),

                Forms\Components\Tabs\Tab::make(__('Dates & prices'))->icon('heroicon-o-currency-euro')->schema([
                    Forms\Components\Section::make(__('Price per person'))->columns(4)->schema([
                        Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR')->required(),
                        Forms\Components\TextInput::make('price_adult')->label(__('Adults'))->numeric(),
                        Forms\Components\TextInput::make('price_child')->label(__('Children'))->numeric(),
                        Forms\Components\TextInput::make('single_supplement')->label(__('Single room supplement'))->numeric(),
                        Forms\Components\TextInput::make('child_age_from')->label(__('Child age from'))->numeric(),
                        Forms\Components\TextInput::make('child_age_to')->label(__('Child age to'))->numeric(),
                        Forms\Components\TextInput::make('deposit_percent')->label(__('Deposit'))->numeric()->suffix('%'),
                        Forms\Components\Placeholder::make('price_from_info')->label(__('Price from (website)'))
                            ->content(fn (?Tour $record) => $record?->price_from ? number_format((float) $record->price_from, 2, ',', '.').' '.$record->currency : '—'),
                    ]),
                    Forms\Components\Section::make(__('Departure dates'))->description(__('Leave the price empty to use the base price.'))->schema([
                        Forms\Components\Repeater::make('departures')->hiddenLabel()->relationship()
                            ->reorderable(false)->collapsible()->cloneable()
                            ->itemLabel(fn (array $state) => trim(($state['start_date'] ?? '').' → '.($state['end_date'] ?? ''), ' →') ?: null)
                            ->addActionLabel(__('Add departure date'))
                            ->columns(6)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')->label(__('Departure'))->native(false)->displayFormat('d.m.Y')->required(),
                                Forms\Components\DatePicker::make('end_date')->label(__('Return'))->native(false)->displayFormat('d.m.Y')->afterOrEqual('start_date'),
                                Forms\Components\TextInput::make('seats_total')->label(__('Seats'))->numeric(),
                                Forms\Components\TextInput::make('seats_booked')->label(__('Booked'))->numeric()->default(0),
                                Forms\Components\TextInput::make('price_adult')->label(__('Adults'))->numeric(),
                                Forms\Components\TextInput::make('price_child')->label(__('Children'))->numeric(),
                                Forms\Components\Select::make('status')->label(__('Status'))->options(DepartureStatus::class)
                                    ->default(DepartureStatus::Available->value)->selectablePlaceholder(false)->columnSpan(2),
                                Forms\Components\Toggle::make('is_last_minute')->label('Last minute')->inline(false)->live(),
                                Forms\Components\TextInput::make('last_minute_price')->label(__('Last minute price'))->numeric()
                                    ->visible(fn (Get $get) => $get('is_last_minute')),
                                Forms\Components\TextInput::make('note')->label(__('Note'))->columnSpan(2),
                            ]),
                    ]),
                    Forms\Components\Section::make(__('Departure places in Macedonia'))->schema([
                        Forms\Components\Repeater::make('departurePlaceLinks')->hiddenLabel()->relationship()
                            ->reorderable(false)->grid(2)->addActionLabel(__('Add departure place'))
                            ->columns(3)
                            ->schema([
                                Forms\Components\Select::make('departure_place_id')->label(__('City'))
                                    ->options(fn (Get $get) => DeparturePlace::query()
                                        ->when(AgencyField::current(fn ($k) => $get('../../'.$k)), fn ($q, $a) => $q->where('agency_id', $a))
                                        ->where('is_active', true)->orderBy('sort_order')->get()->mapWithKeys(fn ($p) => [$p->id => $p->tr('name')]))
                                    ->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                Forms\Components\TextInput::make('departure_time')->label(__('Time'))->placeholder('05:00'),
                                Forms\Components\TextInput::make('surcharge')->label(__('Surcharge'))->numeric()->default(0),
                            ]),
                    ]),
                    Forms\Components\Section::make(__('Additional services & optional excursions'))->schema([
                        Forms\Components\Repeater::make('extras')->hiddenLabel()->relationship()
                            ->orderColumn('sort_order')->collapsible()
                            ->itemLabel(fn (array $state) => $state['name']['mk'] ?? null)
                            ->addActionLabel(__('Add service'))
                            ->columns(4)
                            ->schema([
                                Forms\Components\TextInput::make('name.mk')->label(__('Name').' (МК)')->required()->columnSpan(2),
                                Forms\Components\TextInput::make('name.en')->label(__('Name').' (EN)')->columnSpan(2),
                                Forms\Components\TextInput::make('price')->label(__('Price'))->numeric()->default(0),
                                Forms\Components\Select::make('price_type')->label(__('Charged'))->selectablePlaceholder(false)
                                    ->options(['per_person' => __('per person'), 'per_booking' => __('per booking'), 'per_night' => __('per night')])->default('per_person'),
                                Forms\Components\Select::make('kind')->label(__('Kind'))->selectablePlaceholder(false)
                                    ->options(['service' => __('Service'), 'excursion' => __('Optional excursion'), 'supplement' => __('Supplement'), 'insurance' => __('Insurance')])->default('service'),
                                Forms\Components\Toggle::make('is_optional')->label(__('Optional'))->default(true)->inline(false),
                            ]),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make(__('Included / not included'))->icon('heroicon-o-check-circle')->schema([
                    Lang::tabs(fn (string $l) => [
                        Forms\Components\Grid::make(2)->schema([
                            Lang::lines('included', __('Included in the price'), $l),
                            Lang::lines('not_included', __('Not included in the price'), $l),
                        ]),
                        Lang::editor('notes', __('Note'), $l),
                        Lang::editor('important_info', __('Important information (documents, visa, climate)'), $l),
                        Forms\Components\Grid::make(3)->schema([
                            Lang::area('baggage_info', __('Baggage'), $l),
                            Lang::area('payment_terms', __('Payment terms'), $l),
                            Lang::area('cancellation_policy', __('Cancellation policy'), $l),
                        ]),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make(__('Media & links'))->icon('heroicon-o-film')->schema([
                    CatalogFields::videos(),
                    Forms\Components\FileUpload::make('program_pdf')->label(__('Program / price list (PDF)'))
                        ->disk('public')->directory('programs')->acceptedFileTypes(['application/pdf'])->downloadable()->openable(),
                    Forms\Components\Repeater::make('links')->label(__('Links'))->columns(2)->defaultItems(0)
                        ->addActionLabel(__('Add link'))
                        ->schema([
                            Forms\Components\TextInput::make('label')->label(__('Label'))->required(),
                            Forms\Components\TextInput::make('url')->label('URL')->url()->required(),
                        ]),
                    Forms\Components\Placeholder::make('gallery_hint')->hiddenLabel()
                        ->content(__('Photos are added in the "Gallery" tab after saving.')),
                ]),

                Forms\Components\Tabs\Tab::make(__('Promotions & booking'))->icon('heroicon-o-bolt')->schema([
                    CatalogFields::promotions(),
                    Forms\Components\Section::make(__('Booking'))->columns(2)->schema([
                        Forms\Components\Select::make('booking_mode')->label(__('Booking mode'))->selectablePlaceholder(false)
                            ->options(['request' => __('On request (agency confirms)'), 'instant' => __('Instant confirmation')])->default('request'),
                        Forms\Components\TextInput::make('expert_code')->label(__('Code in Expert'))
                            ->helperText(__('For automatic availability check once Expert is connected.')),
                    ]),
                    CatalogFields::seo(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['destination'])->withCount('departures')
                ->withMin(['departures as next_departure' => fn ($q) => $q->whereDate('start_date', '>=', today())], 'start_date'))
            ->columns([
                CatalogFields::coverColumn(),
                CatalogFields::titleColumn()->description(fn (Tour $r) => collect([$r->location, $r->duration_days ? $r->duration_days.' '.__('days') : null])->filter()->implode(' · ')),
                Tables\Columns\TextColumn::make('tour_type')->label(__('Type'))->badge()->toggleable(),
                Tables\Columns\TextColumn::make('destination.name')->label(__('Destination'))->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('next_departure')->label(__('Next departure'))->date('d.m.Y')->placeholder('—')
                    ->description(fn (Tour $r) => $r->departures_count ? __('Dates').': '.$r->departures_count : null)->sortable(),
                Tables\Columns\TextColumn::make('price_from')->label(__('Price from'))->sortable()
                    ->formatStateUsing(fn ($state, Tour $r) => $state ? number_format((float) $state, 0, ',', '.').' '.$r->currency : '—'),
                CatalogFields::lastMinuteColumn(),
                Tables\Columns\IconColumn::make('is_flight')->label(__('Transport'))->boolean()->trueIcon('heroicon-o-paper-airplane')->falseIcon('heroicon-o-truck')
                    ->trueColor('info')->falseColor('gray')->tooltip(fn (Tour $r) => $r->is_flight ? __('Flight tour') : __('Bus tour'))->toggleable(),
                AgencyField::column()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_featured')->label(__('Featured')),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('tour_type')->label(__('Tour type'))->options(TourType::class),
                Tables\Filters\SelectFilter::make('destination_id')->label(__('Destination'))
                    ->options(fn () => GroupResource::optionsFor(GroupType::Destination, null))->searchable(),
                CatalogFields::lastMinuteFilter(),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                Tables\Filters\Filter::make('upcoming')->label(__('With upcoming departures'))
                    ->query(fn (Builder $query) => $query->whereHas('departures', fn ($q) => $q->whereDate('start_date', '>=', today()))),
            ])
            ->actions([
                Tables\Actions\Action::make('duplicate')->label(__('Duplicate'))->icon('heroicon-o-document-duplicate')->color('gray')->iconButton()
                    ->requiresConfirmation()
                    ->action(function (Tour $record, $livewire) {
                        $copy = static::duplicate($record);
                        $livewire->redirect(static::getUrl('edit', ['record' => $copy]));
                    }),
                Tables\Actions\EditAction::make()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')->label(__('Activate'))->icon('heroicon-o-eye')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')->label(__('Deactivate'))->icon('heroicon-o-eye-slash')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->striped();
    }

    /** Copy a tour with program, extras and departure places (not the dates) */
    public static function duplicate(Tour $tour): Tour
    {
        $copy = $tour->replicate(['slug', 'price_from', 'cover_image']);
        $copy->title = collect($tour->title ?? [])->map(fn ($v) => $v ? $v.' (копија)' : $v)->all();
        $copy->is_active = false;
        $copy->save();

        foreach ($tour->days as $d) {
            $copy->days()->create($d->only(['day_number', 'title', 'description', 'meals', 'overnight', 'sort_order']));
        }
        foreach ($tour->extras as $e) {
            $copy->extras()->create($e->only(['name', 'description', 'price', 'price_type', 'kind', 'is_optional', 'sort_order']));
        }
        foreach ($tour->departurePlaceLinks as $p) {
            $copy->departurePlaceLinks()->create($p->only(['departure_place_id', 'departure_time', 'surcharge']));
        }
        foreach ($tour->media as $m) {
            $copy->media()->create($m->only(['path', 'caption', 'is_main', 'sort_order']));
        }

        return $copy;
    }

    public static function getRelations(): array
    {
        return [MediaRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}
