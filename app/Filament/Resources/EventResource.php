<?php

namespace App\Filament\Resources;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Enums\GroupType;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\RelationManagers\MediaRelationManager;
use App\Filament\Resources\EventResource\Pages;
use App\Filament\Support\AgencyField;
use App\Filament\Support\CatalogFields;
use App\Filament\Support\Lang;
use App\Models\Event;
use App\Models\Tour;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EventResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Event::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 2;

    public static function permissionModule(): string
    {
        return 'events';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Event'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Events');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return (string) $record?->tr('title');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('event')->persistTabInQueryString()->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make(__('Basic information'))->icon('heroicon-o-information-circle')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Section::make()->columnSpan(2)->columns(2)->schema([
                            AgencyField::select(),
                            Lang::tabs(fn (string $l) => [
                                Lang::text('title', __('Title'), $l, true),
                                Lang::area('short_description', __('Short description'), $l, 2),
                            ]),
                            Forms\Components\Select::make('category')->label(__('Category'))->options(EventCategory::class)
                                ->default(EventCategory::Concert->value)->required(),
                            Forms\Components\TextInput::make('performer')->label(__('Performer / teams')),
                            Forms\Components\TextInput::make('venue')->label(__('Venue')),
                            Forms\Components\TextInput::make('organizer')->label(__('Organizer')),
                            Forms\Components\TextInput::make('city')->label(__('City')),
                            Forms\Components\TextInput::make('country')->label(__('Country')),
                            Forms\Components\TextInput::make('address')->label(__('Address'))->columnSpanFull(),
                            ...CatalogFields::location(),
                        ]),
                        Forms\Components\Group::make()->columnSpan(1)->schema([
                            CatalogFields::photos('events'),
                            Forms\Components\Section::make(__('Date & time'))->columns(2)->schema([
                                Forms\Components\DateTimePicker::make('starts_at')->label(__('Starts'))->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->required()->columnSpanFull(),
                                Forms\Components\DateTimePicker::make('ends_at')->label(__('Ends'))->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->columnSpanFull(),
                                Forms\Components\TextInput::make('doors_open')->label(__('Doors open'))->placeholder('19:00'),
                                Forms\Components\TextInput::make('age_restriction')->label(__('Age limit'))->placeholder('16+'),
                                Forms\Components\Select::make('status')->label(__('Status'))->options(EventStatus::class)
                                    ->default(EventStatus::Scheduled->value)->selectablePlaceholder(false)->columnSpanFull(),
                            ]),
                            CatalogFields::publishing(),
                        ]),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make(__('Description'))->icon('heroicon-o-document-text')->schema([
                    Lang::tabs(fn (string $l) => [
                        Lang::editor('description', __('Description'), $l),
                        Forms\Components\Grid::make(2)->schema([
                            Lang::lines('included', __('Included in the price'), $l),
                            Lang::lines('not_included', __('Not included in the price'), $l),
                        ]),
                        Lang::editor('notes', __('Note'), $l),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make(__('Tickets'))->icon('heroicon-o-ticket')->schema([
                    Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR'),
                    Forms\Components\Repeater::make('tickets')->label(__('Ticket types'))->relationship()->defaultItems(0)
                        ->orderColumn('sort_order')->collapsible()->addActionLabel(__('Add ticket type'))
                        ->itemLabel(fn (array $state) => $state['name']['mk'] ?? null)
                        ->columns(4)
                        ->schema([
                            Forms\Components\TextInput::make('name.mk')->label(__('Name').' (МК)')->required()->placeholder('Партер / VIP / Трибина'),
                            Forms\Components\TextInput::make('name.en')->label(__('Name').' (EN)'),
                            Forms\Components\TextInput::make('price')->label(__('Sale price'))->numeric()->required(),
                            Forms\Components\TextInput::make('cost_price')->label(__('Organizer price (cost)'))->numeric()
                                ->helperText(__('What the agency pays per ticket – for the profit report')),
                            Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true)->inline(false),
                            Forms\Components\TextInput::make('quantity')->label(__('Quantity'))->numeric()->helperText(__('Empty = unlimited')),
                            Forms\Components\TextInput::make('sold')->label(__('Sold'))->numeric()->default(0),
                            Forms\Components\TextInput::make('description.mk')->label(__('Description').' (МК)')->columnSpan(2),
                        ]),
                    Forms\Components\TextInput::make('external_ticket_url')->label(__('External ticket link'))->url(),
                    Forms\Components\Select::make('tour_id')->label(__('Organized trip to the event'))
                        ->helperText(__('Link an organized tour (transport / hotel package) for this event.'))
                        ->options(fn (Get $get) => Tour::query()->when(AgencyField::current($get), fn ($q, $a) => $q->where('agency_id', $a))
                            ->latest()->limit(300)->get()->mapWithKeys(fn ($t) => [$t->id => $t->tr('title')]))
                        ->searchable(),
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
            ->columns([
                CatalogFields::coverColumn(),
                CatalogFields::titleColumn()->description(fn (Event $r) => collect([$r->venue, $r->city])->filter()->implode(' · ')),
                Tables\Columns\TextColumn::make('category')->label(__('Category'))->badge(),
                Tables\Columns\TextColumn::make('starts_at')->label(__('Date'))->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('price_from')->label(__('Price from'))
                    ->formatStateUsing(fn ($state, Event $r) => $state ? number_format((float) $state, 0, ',', '.').' '.$r->currency : '—'),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge(),
                CatalogFields::lastMinuteColumn(),
                AgencyField::column()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_featured')->label(__('Featured')),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('category')->label(__('Category'))->options(EventCategory::class),
                Tables\Filters\Filter::make('upcoming')->label(__('Upcoming only'))->default()
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '>=', now()->startOfDay()))),
                CatalogFields::lastMinuteFilter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Duplicate::action(function (Event $from, Event $to) {
                    foreach ($from->tickets as $t) {
                        $to->tickets()->create($t->only(['name', 'description', 'price', 'cost_price', 'quantity', 'is_active', 'sort_order']) + ['sold' => 0]);
                    }
                    Duplicate::media($from, $to);
                }),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('starts_at');
    }

    public static function getRelations(): array
    {
        return [MediaRelationManager::class, \App\Filament\RelationManagers\CostsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
