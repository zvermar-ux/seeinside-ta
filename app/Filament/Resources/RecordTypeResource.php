<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecordTypeResource\Pages;
use App\Models\RecordType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Super admin: new kinds of offers (e.g. Transport) with their own fields */
class RecordTypeResource extends Resource
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = RecordType::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'administration';

    protected static ?int $navigationSort = 20;

    public static function can(string $action, ?Model $record = null): bool
    {
        return (bool) Auth::user()?->isSuperAdmin();
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Record type'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Record types');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(4)->schema([
                Forms\Components\TextInput::make('name.mk')->label(__('Name (plural)').' (МК)')->placeholder('Трансфери')->required(),
                Forms\Components\TextInput::make('name.en')->label(__('Name (plural)').' (EN)')->placeholder('Transfers'),
                Forms\Components\TextInput::make('singular.mk')->label(__('One record').' (МК)')->placeholder('Трансфер'),
                Forms\Components\TextInput::make('singular.en')->label(__('One record').' (EN)')->placeholder('Transfer'),
                Forms\Components\TextInput::make('slug')->label(__('Code'))->helperText(__('Latin letters, used in the API and website address. Empty = from the name.'))->maxLength(40)
                    ->disabled(fn (?RecordType $record) => $record?->records()->exists())->dehydrated(),
                Forms\Components\Select::make('icon')->label(__('Icon'))->options(RecordType::icons())->default('heroicon-o-squares-2x2')->selectablePlaceholder(false),
                Forms\Components\Select::make('price_label')->label(__('Price is'))->placeholder(__('No price'))
                    ->options(['per_person' => __('per person'), 'per_booking' => __('per booking / vehicle'), 'per_day' => __('per day'), 'per_night' => __('per night')]),
                Forms\Components\TextInput::make('sort_order')->label(__('Order'))->numeric()->default(0),
                Forms\Components\Toggle::make('bookable')->label(__('Can be booked / requested on the website'))->default(true)->columnSpan(2),
                Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true)->columnSpan(2),
            ]),
            Forms\Components\Section::make(__('Fields'))
                ->description(__('Title, description, main photo, gallery and price exist for every type. Add the fields this type needs, e.g. Vehicle, Seats, Departure time, Route.'))
                ->schema([
                    Forms\Components\Repeater::make('fields')->hiddenLabel()->columns(6)->reorderable()->collapsible()->cloneable()->defaultItems(0)
                        ->addActionLabel(__('Add field'))
                        ->itemLabel(fn (array $state) => trim(($state['label']['mk'] ?? '').' · '.(RecordType::fieldTypes()[$state['type'] ?? ''] ?? '')))
                        ->schema([
                            Forms\Components\TextInput::make('label.mk')->label(__('Label').' (МК)')->required()->columnSpan(2),
                            Forms\Components\TextInput::make('label.en')->label(__('Label').' (EN)')->columnSpan(2),
                            Forms\Components\Select::make('type')->label(__('Field type'))->options(RecordType::fieldTypes())->required()->default('text')->live()->columnSpan(2),
                            Forms\Components\TagsInput::make('options')->label(__('Choices'))->columnSpan(4)->placeholder(__('Type a choice and press Enter'))
                                ->visible(fn (Get $get) => in_array($get('type'), ['select', 'multiselect'], true)),
                            Forms\Components\TextInput::make('suffix')->label(__('Unit after the value'))->placeholder(__('e.g. seats, km, min'))->columnSpan(2)
                                ->visible(fn (Get $get) => in_array($get('type'), ['number', 'text'], true)),
                            Forms\Components\TextInput::make('key')->label(__('Code'))->helperText(__('Empty = from the label'))->maxLength(40)->columnSpan(2),
                            Forms\Components\Toggle::make('required')->label(__('Required'))->inline(false),
                            Forms\Components\Toggle::make('show_in_list')->label(__('Column in the list'))->inline(false),
                            Forms\Components\Toggle::make('show_on_website')->label(__('Shown on the website'))->default(true)->inline(false),
                            Forms\Components\Toggle::make('filterable')->label(__('Filter on the website'))->inline(false)
                                ->visible(fn (Get $get) => in_array($get('type'), ['select', 'multiselect', 'toggle'], true)),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\IconColumn::make('icon')->label('')->icon(fn ($state) => $state ?: 'heroicon-o-squares-2x2'),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->state(fn (RecordType $r) => $r->label())->description(fn (RecordType $r) => $r->slug)->weight('medium'),
                Tables\Columns\TextColumn::make('fields_count')->label(__('Fields'))->state(fn (RecordType $r) => count($r->fieldDefs()))->badge()->color('gray'),
                Tables\Columns\TextColumn::make('records_count')->label(__('Records'))->counts('records')->badge(),
                Tables\Columns\IconColumn::make('bookable')->label(__('Booking'))->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label(__('Active'))->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()->iconButton(), Tables\Actions\DeleteAction::make()->iconButton()->visible(fn (RecordType $r) => ! $r->records()->exists())])
            ->emptyStateHeading(__('No record types yet'))
            ->emptyStateDescription(__('Example: “Transport” with fields Vehicle, Seats, Route, Departure time. It appears in the menu of the agencies that have it turned on (Agencies → Modules).'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecordTypes::route('/'),
            'create' => Pages\CreateRecordType::route('/create'),
            'edit' => Pages\EditRecordType::route('/{record}/edit'),
        ];
    }
}
