<?php

namespace App\Filament\Resources;

use App\Enums\GroupType;
use App\Enums\ObjectType;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\TravelObjectResource\Pages;
use App\Filament\Resources\TravelObjectResource\RelationManagers\ImagesRelationManager;
use App\Filament\Support\AgencyField;
use App\Models\TourOperator;
use App\Models\TravelObject;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TravelObjectResource extends Resource
{
    use GuardedByPermission, Translatable;

    protected static ?string $model = TravelObject::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionModule(): string
    {
        return 'objects';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Arrangement / hotel'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Imported offer (Aries)');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'place', 'country'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            __('Type') => $record->type?->getLabel(),
            __('Place') => trim($record->place.', '.$record->country, ', '),
        ]);
    }

    public static function operatorOptions(?int $agencyId): array
    {
        return TourOperator::query()
            ->when($agencyId, fn (Builder $q) => $q->where('agency_id', $agencyId))
            ->orderByDesc('is_own')->orderBy('name')
            ->pluck('name', 'id')->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Group::make()->columnSpan(2)->schema([
                    Forms\Components\Section::make(__('Basic information'))->columns(2)->schema([
                        AgencyField::select(),
                        Forms\Components\ToggleButtons::make('type')->label(__('Type'))
                            ->options(ObjectType::class)->inline()->required()->default(ObjectType::Arrangement->value),
                        Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->columnSpanFull(),
                        Forms\Components\Select::make('tour_operator_id')->label(__('Tour operator'))
                            ->options(fn (Get $get) => static::operatorOptions(AgencyField::current($get)))
                            ->searchable(),
                        Forms\Components\Select::make('stars')->label(__('Category'))
                            ->options([0 => '—', 1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']),
                        Forms\Components\TextInput::make('room')->label(__('Room / accommodation')),
                        Forms\Components\TextInput::make('board')->label(__('Board / service'))->placeholder('BB, HB, AI…'),
                    ]),
                    Forms\Components\Section::make(__('Location'))->columns(3)->schema([
                        Forms\Components\TextInput::make('place')->label(__('Place')),
                        Forms\Components\TextInput::make('region')->label(__('Region')),
                        Forms\Components\TextInput::make('country')->label(__('Country')),
                        Forms\Components\TextInput::make('map_location')->label(__('Map (lat,lng or address)'))
                            ->placeholder('40.845640,25.869948')->live(onBlur: true)->columnSpanFull(),
                        Forms\Components\Placeholder::make('map_preview')->hiddenLabel()->columnSpanFull()
                            ->visible(fn (Get $get) => filled($get('map_location')))
                            ->content(fn (Get $get) => new HtmlString(
                                '<iframe class="ta-map" loading="lazy" src="https://maps.google.com/maps?q='.urlencode((string) $get('map_location')).'&z=13&output=embed"></iframe>'
                            )),
                    ]),
                    Forms\Components\Section::make(__('Description'))->schema([
                        Forms\Components\RichEditor::make('description')->label(__('Description'))
                            ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('objects/attachments'),
                        Forms\Components\RichEditor::make('service_description')->label(__('Service / accommodation description'))
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                    ]),
                ]),
                Forms\Components\Group::make()->columnSpan(1)->schema([
                    Forms\Components\Section::make(__('Publishing'))->schema([
                        Forms\Components\Toggle::make('is_active')->label(__('Active on website')),
                        Forms\Components\Toggle::make('is_featured')->label(__('Featured (suggestion)')),
                        Forms\Components\Select::make('group_id')->label(__('Group'))
                            ->options(fn (Get $get) => GroupResource::optionsFor(GroupType::Group, AgencyField::current($get)))
                            ->searchable(),
                        Forms\Components\Select::make('destination_id')->label(__('Destination'))
                            ->options(fn (Get $get) => GroupResource::optionsFor(GroupType::Destination, AgencyField::current($get)))
                            ->searchable(),
                    ]),
                    Forms\Components\Section::make(__('Prices'))->schema([
                        Forms\Components\TextInput::make('markup_percent')->label(__('Markup'))->numeric()->suffix('%')->default(0)->minValue(-100)->maxValue(1000),
                        Forms\Components\TextInput::make('min_payment_percent')->label(__('Minimum advance payment'))->numeric()->suffix('%')->default(100)->minValue(0)->maxValue(100),
                    ]),
                    Forms\Components\Section::make(__('Source'))->collapsible()->collapsed()
                        ->visible(fn (?TravelObject $record) => filled($record?->external_source))
                        ->schema([
                            Forms\Components\Placeholder::make('src')->label(__('Imported from'))
                                ->content(fn (?TravelObject $record) => strtoupper((string) $record?->external_source).' #'.$record?->external_id.($record?->external_service_id ? ' / '.$record->external_service_id : '')),
                            Forms\Components\Placeholder::make('synced')->label(__('Last sync'))
                                ->content(fn (?TravelObject $record) => $record?->synced_at?->format('d.m.Y H:i') ?? '—'),
                        ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['destination', 'group', 'tourOperator']))
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->label('')->disk('public')->square()->size(52)
                    ->defaultImageUrl(fn () => 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M21 16l-5-5-8 8"/></svg>')),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->weight('semibold')->wrap()
                    ->extraAttributes(['style' => 'min-width:16rem'])
                    ->description(fn (TravelObject $r) => collect([$r->place, $r->region !== '/' ? $r->region : null, $r->country])->filter()->implode(' · ')),
                Tables\Columns\TextColumn::make('type')->label(__('Type'))->badge()->toggleable(),
                Tables\Columns\TextColumn::make('stars')->label(__('Cat.'))
                    ->formatStateUsing(fn ($state) => $state ? str_repeat('★', (int) $state) : '')
                    ->color('warning')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('room')->label(__('Room'))->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('board')->label(__('Service'))->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tourOperator.name')->label(__('Tour operator'))->sortable()->toggleable()->wrap(),
                AgencyField::column()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('group.name')->label(__('Group / destination'))->toggleable()->placeholder('—')
                    ->description(fn (TravelObject $r) => $r->destination?->display_name),
                Tables\Columns\TextColumn::make('images_count')->counts('images')->label(__('Images'))->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'danger')->sortable()->toggleable(),
                Tables\Columns\TextInputColumn::make('markup_percent')->label(__('Markup %'))
                    ->type('number')->rules(['numeric', 'between:-100,1000'])->width('90px')->toggleable(),
                Tables\Columns\TextColumn::make('min_payment_percent')->label(__('Advance'))->suffix('%')->alignEnd()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_featured')->label(__('Featured')),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('tour_operator_id')->label(__('Tour operator'))
                    ->options(fn () => static::operatorOptions(null))->searchable(),
                Tables\Filters\SelectFilter::make('group_id')->label(__('Group'))
                    ->options(fn () => GroupResource::optionsFor(GroupType::Group, null))->searchable(),
                Tables\Filters\SelectFilter::make('destination_id')->label(__('Destination'))
                    ->options(fn () => GroupResource::optionsFor(GroupType::Destination, null))->searchable(),
                Tables\Filters\SelectFilter::make('country')->label(__('Country'))
                    ->options(fn () => TravelObject::query()->whereNotNull('country')->where('country', '!=', '')->distinct()->orderBy('country')->pluck('country', 'country')->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('stars')->label(__('Category'))
                    ->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                Tables\Filters\TernaryFilter::make('is_featured')->label(__('Featured')),
                Tables\Filters\TernaryFilter::make('has_images')->label(__('Images'))
                    ->trueLabel(__('With images'))->falseLabel(__('Without images'))
                    ->queries(
                        true: fn (Builder $query) => $query->has('images'),
                        false: fn (Builder $query) => $query->doesntHave('images'),
                    ),
                Tables\Filters\TernaryFilter::make('without_group')->label(__('Without group/destination'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('group_id')->whereNull('destination_id'),
                        false: fn (Builder $query) => $query->where(fn ($q) => $q->whereNotNull('group_id')->orWhereNotNull('destination_id')),
                    ),
            ])
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->actionsPosition(Tables\Enums\ActionsPosition::BeforeColumns)
            ->actions([
                Tables\Actions\Action::make('convert')->label(__('Create website offer'))->icon('heroicon-o-arrow-up-on-square')->color('success')->iconButton()
                    ->tooltip(fn (TravelObject $r) => $r->type === ObjectType::Arrangement ? __('Create an organized tour from this record') : __('Create a hotel / apartment from this record'))
                    ->requiresConfirmation()
                    ->modalDescription(__('A new, inactive record is created with the name, description, location and photos. Prices and dates are added there.'))
                    ->action(function (TravelObject $record, $livewire) {
                        $url = \App\Services\Aries\AriesConverter::convert($record);
                        Notification::make()->success()->title(__('Created'))->send();
                        $livewire->redirect($url);
                    }),
                Tables\Actions\EditAction::make(),
                Duplicate::action(function (TravelObject $from, TravelObject $to) {
                    foreach ($from->images as $img) {
                        \App\Models\ObjectImage::withoutEvents(fn () => $to->images()->create($img->only(['path', 'caption', 'is_main', 'sort_order'])));
                    }
                }, ['external_service_id', 'synced_at']),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')->label(__('Activate'))->icon('heroicon-o-eye')
                        ->action(fn (Collection $records) => static::bulkUpdate($records, ['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')->label(__('Deactivate'))->icon('heroicon-o-eye-slash')
                        ->action(fn (Collection $records) => static::bulkUpdate($records, ['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('assign')->label(__('Assign group / destination'))->icon('heroicon-o-map')
                        ->form([
                            Forms\Components\Select::make('group_id')->label(__('Group'))
                                ->options(fn () => GroupResource::optionsFor(GroupType::Group, null))->searchable(),
                            Forms\Components\Select::make('destination_id')->label(__('Destination'))
                                ->options(fn () => GroupResource::optionsFor(GroupType::Destination, null))->searchable(),
                        ])
                        ->action(fn (Collection $records, array $data) => static::bulkUpdate($records, array_filter($data, fn ($v) => filled($v))))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('prices')->label(__('Set markup / advance'))->icon('heroicon-o-receipt-percent')
                        ->form([
                            Forms\Components\TextInput::make('markup_percent')->label(__('Markup'))->numeric()->suffix('%'),
                            Forms\Components\TextInput::make('min_payment_percent')->label(__('Minimum advance payment'))->numeric()->suffix('%')->minValue(0)->maxValue(100),
                        ])
                        ->action(fn (Collection $records, array $data) => static::bulkUpdate($records, array_filter($data, fn ($v) => filled($v))))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('convert')->label(__('Create website offer'))->icon('heroicon-o-arrow-up-on-square')->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(__('Arrangements become organized tours, hotels and apartments become hotels / apartments. New records are inactive.'))
                        ->action(function (Collection $records) {
                            $records->each(fn (TravelObject $r) => \App\Services\Aries\AriesConverter::convert($r));
                            Notification::make()->success()->title(__('Created :count records', ['count' => $records->count()]))->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([25, 50, 100, 250]);
    }

    protected static function bulkUpdate(Collection $records, array $data): void
    {
        if ($data === []) {
            return;
        }

        TravelObject::whereKey($records->modelKeys())->update($data);

        Notification::make()->success()->title(__('Updated :count records', ['count' => $records->count()]))->send();
    }

    public static function getRelations(): array
    {
        return [ImagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTravelObjects::route('/'),
            'create' => Pages\CreateTravelObject::route('/create'),
            'edit' => Pages\EditTravelObject::route('/{record}/edit'),
        ];
    }
}
