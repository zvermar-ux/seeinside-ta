<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AirlineResource\Pages;
use App\Models\Airline;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Shared list for all agencies: everyone with catalog settings can view, only the super admin edits */
class AirlineResource extends Resource
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Airline::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 2;

    public static function can(string $action, ?Model $record = null): bool
    {
        $user = Auth::user();

        return $user && ($user->isSuperAdmin() || (in_array($action, ['viewAny', 'view'], true) && $user->can('catalog_settings.view')));
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Airline'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Airlines');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('iata_code')->label('IATA')->maxLength(3),
            Forms\Components\FileUpload::make('logo')->label(__('Logo'))->image()->disk('public')->directory('airlines'),
            Forms\Components\Toggle::make('is_low_cost')->label(__('Low-cost')),
            Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')->label('')->disk('public')->height(28),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->weight('semibold'),
                Tables\Columns\TextColumn::make('iata_code')->label('IATA')->badge()->color('gray'),
                Tables\Columns\IconColumn::make('is_low_cost')->label(__('Low-cost'))->boolean(),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active'))->disabled(fn () => ! Auth::user()->isSuperAdmin()),
            ])
            ->actions([Tables\Actions\EditAction::make(), Duplicate::action()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAirlines::route('/')];
    }
}
