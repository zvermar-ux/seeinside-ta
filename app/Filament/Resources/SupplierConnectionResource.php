<?php

namespace App\Filament\Resources;

use App\Enums\SupplierProvider;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\SupplierConnectionResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\SupplierConnection;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierConnectionResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = SupplierConnection::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 5;

    public static function permissionModule(): string
    {
        return 'integrations';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Booking system connection'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Booking systems (API)');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                AgencyField::select(),
                Forms\Components\Select::make('provider')->label(__('System'))->options(SupplierProvider::class)->required()->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => blank($get('name')) ? $set('name', SupplierProvider::tryFrom((string) $state)?->getLabel()) : null),
                Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
                Forms\Components\TextInput::make('base_url')->label(__('API address (URL)'))->url(),
                Forms\Components\TextInput::make('username')->label(__('Username / agency code')),
                Forms\Components\KeyValue::make('credentials')->label(__('Access keys'))->columnSpanFull()
                    ->keyLabel(__('Name'))->valueLabel(__('Value'))
                    ->helperText(__('API key, secret, password… stored encrypted.')),
                Forms\Components\TextInput::make('settings.availability_url')->label(__('Availability address (JSON)'))->columnSpanFull()
                    ->placeholder('https://partner.mk/api/availability?hotel={hotel}&from={from}&to={to}')
                    ->helperText(__('For systems that give free rooms as a simple JSON feed. {hotel} = hotel code, {from}/{to} = dates. The access key is taken from "api_key" in the keys above.'))
                    ->visible(fn (Forms\Get $get) => in_array($get('provider'), ['expert', 'other'], true)),
                Forms\Components\Toggle::make('is_active')->label(__('Active')),
                Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->weight('semibold'),
                Tables\Columns\TextColumn::make('provider')->label(__('System'))->badge(),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('last_sync_at')->label(__('Last sync'))->since()->placeholder('—'),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->actions([Tables\Actions\EditAction::make(), Duplicate::action(), Tables\Actions\DeleteAction::make()])
            ->emptyStateDescription(__('Hotelbeds, Filos, Bros, Seayou, People of the World, Expert, GOL IBE… The live connection is built once the supplier gives API access.'));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSupplierConnections::route('/')];
    }
}
