<?php

namespace App\Filament\Resources\AccommodationResource\RelationManagers;

use App\Enums\Board;
use App\Enums\RoomType;
use App\Models\AccommodationRoom;
use App\Models\Amenity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    protected static ?string $icon = 'heroicon-o-key';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Rooms & price list').' ('.$ownerRecord->rooms()->count().')';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->columns(4)->schema([
            Forms\Components\TextInput::make('name.mk')->label(__('Room name').' (МК)')->required()->columnSpan(2)->placeholder('Двокреветна соба со поглед на море'),
            Forms\Components\TextInput::make('name.en')->label(__('Room name').' (EN)')->columnSpan(2),
            Forms\Components\Select::make('room_type')->label(__('Room type'))->options(RoomType::class)->default(RoomType::Double->value)->required(),
            Forms\Components\Select::make('board')->label(__('Board'))->options(Board::class)->default(Board::Bb->value)->required(),
            Forms\Components\TextInput::make('max_adults')->label(__('Max adults'))->numeric()->default(2)->required(),
            Forms\Components\TextInput::make('max_children')->label(__('Max children'))->numeric()->default(0)->required(),
            Forms\Components\TextInput::make('beds')->label(__('Beds'))->placeholder('1 брачен + 1 помошен'),
            Forms\Components\TextInput::make('size_m2')->label(__('Size (m²)'))->numeric(),
            Forms\Components\TextInput::make('units')->label(__('Number of units'))->numeric(),
            Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true)->inline(false),
            Forms\Components\TextInput::make('price')->label(__('Base price per night'))->numeric(),
            Forms\Components\Select::make('price_type')->label(__('Price is'))->selectablePlaceholder(false)
                ->options(['per_room' => __('per room'), 'per_person' => __('per person')])->default('per_room'),
            Forms\Components\CheckboxList::make('room_amenities')->label(__('Room amenities'))->columnSpanFull()->columns(4)
                ->options(fn () => Amenity::where('is_active', true)->whereIn('category', ['room', 'general'])->orderBy('sort_order')->get()->mapWithKeys(fn ($a) => [$a->key => $a->tr('name')])),
            Forms\Components\Textarea::make('description.mk')->label(__('Description').' (МК)')->rows(2)->columnSpan(2),
            Forms\Components\Textarea::make('description.en')->label(__('Description').' (EN)')->rows(2)->columnSpan(2),
            Forms\Components\Repeater::make('rates')->label(__('Seasonal prices'))->relationship()->columnSpanFull()
                ->reorderable(false)->collapsible()->addActionLabel(__('Add period'))->defaultItems(0)
                ->itemLabel(fn (array $state) => trim(($state['date_from'] ?? '').' → '.($state['date_to'] ?? '').(isset($state['price']) ? '  ·  '.$state['price'] : ''), ' →'))
                ->columns(6)
                ->schema([
                    Forms\Components\DatePicker::make('date_from')->label(__('From'))->native(false)->displayFormat('d.m.Y')->required(),
                    Forms\Components\DatePicker::make('date_to')->label(__('To'))->native(false)->displayFormat('d.m.Y')->required()->afterOrEqual('date_from'),
                    Forms\Components\TextInput::make('price')->label(__('Price per night'))->numeric()->required(),
                    Forms\Components\Select::make('price_type')->label(__('Price is'))->selectablePlaceholder(false)
                        ->options(['per_room' => __('per room'), 'per_person' => __('per person')])->default('per_room'),
                    Forms\Components\TextInput::make('min_nights')->label(__('Min. nights'))->numeric()->default(1),
                    Forms\Components\TextInput::make('release_days')->label(__('Release (days)'))->numeric(),
                    Forms\Components\Toggle::make('is_last_minute')->label('Last minute')->inline(false),
                    Forms\Components\TextInput::make('note')->label(__('Note'))->columnSpan(5),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->withCount('rates')->withMin('rates', 'price'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Room'))->weight('semibold')
                    ->state(fn (AccommodationRoom $r) => $r->tr('name'))
                    ->description(fn (AccommodationRoom $r) => $r->beds),
                Tables\Columns\TextColumn::make('room_type')->label(__('Room type'))->badge()->color('gray'),
                Tables\Columns\TextColumn::make('board')->label(__('Board'))->badge()->color('info'),
                Tables\Columns\TextColumn::make('capacity')->label(__('Capacity'))
                    ->state(fn (AccommodationRoom $r) => $r->max_adults.' + '.$r->max_children),
                Tables\Columns\TextColumn::make('price')->label(__('Base price'))->placeholder('—')
                    ->formatStateUsing(fn ($state, AccommodationRoom $r) => $state.' / '.($r->price_type === 'per_person' ? __('per person') : __('per room'))),
                Tables\Columns\TextColumn::make('rates_count')->label(__('Periods'))->badge()
                    ->description(fn (AccommodationRoom $r) => $r->rates_min_price ? __('from').' '.$r->rates_min_price : null),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label(__('Add room'))->icon('heroicon-o-plus')->modalWidth('6xl')])
            ->actions([
                Tables\Actions\ReplicateAction::make()->iconButton()->excludeAttributes(['rates_count', 'rates_min_price']),
                Tables\Actions\EditAction::make()->iconButton()->modalWidth('6xl'),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading(__('No rooms yet'))
            ->emptyStateDescription(__('Add room types with board, capacity and prices per period.'));
    }
}
