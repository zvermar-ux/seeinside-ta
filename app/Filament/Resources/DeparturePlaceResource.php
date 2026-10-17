<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\DeparturePlaceResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\DeparturePlace;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeparturePlaceResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = DeparturePlace::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 1;

    public static function permissionModule(): string
    {
        return 'catalog_settings';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Departure place'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Departure places');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                AgencyField::select(),
                Forms\Components\TextInput::make('name.mk')->label(__('Name').' (МК)')->required(),
                Forms\Components\TextInput::make('name.en')->label(__('Name').' (EN)'),
                Forms\Components\TextInput::make('meeting_point')->label(__('Meeting point'))->placeholder('Паркинг кај Градски трговски центар'),
                Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')->defaultSort('sort_order')->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->weight('semibold')
                    ->state(fn (DeparturePlace $r) => $r->tr('name'))->description(fn (DeparturePlace $r) => $r->meeting_point),
                AgencyField::column(),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([AgencyField::filter()])
            ->actions([Tables\Actions\EditAction::make(), Duplicate::action(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDeparturePlaces::route('/')];
    }
}
