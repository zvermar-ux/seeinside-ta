<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Persons (passengers) and companies – "Фирми / лица" */
class ClientResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Client::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 3;

    public static function permissionModule(): string
    {
        return 'clients';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Client'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Clients & companies');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'company_name', 'passport_no', 'phone', 'email'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->display_name;
    }

    /** Form fields of a person / company – also used for "new client" inside the booking form */
    public static function fields(bool $compact = false): array
    {
        return [
            Forms\Components\ToggleButtons::make('type')->hiddenLabel()->inline()->live()->default('person')->columnSpanFull()
                ->options(['person' => __('Person'), 'company' => __('Company')])->icons(['person' => 'heroicon-m-user', 'company' => 'heroicon-m-building-office']),
            Forms\Components\TextInput::make('company_name')->label(__('Company name'))->required()->columnSpanFull()
                ->visible(fn (Get $get) => $get('type') === 'company'),
            Forms\Components\Select::make('title')->label(__('Title'))->options(['Mr' => 'Mr', 'Mrs' => 'Mrs', 'Ms' => 'Ms', 'Chd' => 'Chd', 'Inf' => 'Inf'])
                ->visible(fn (Get $get) => $get('type') !== 'company'),
            Forms\Components\TextInput::make('first_name')->label(fn (Get $get) => $get('type') === 'company' ? __('Contact first name') : __('First name'))->required(fn (Get $get) => $get('type') !== 'company'),
            Forms\Components\TextInput::make('last_name')->label(fn (Get $get) => $get('type') === 'company' ? __('Contact last name') : __('Last name'))->required(fn (Get $get) => $get('type') !== 'company'),
            Forms\Components\Select::make('gender')->label(__('Gender'))->options(['M' => __('Male'), 'F' => __('Female')])->visible(fn (Get $get) => $get('type') !== 'company'),
            Forms\Components\DatePicker::make('birth_date')->label(__('Date of birth'))->native(false)->displayFormat('d.m.Y')->maxDate(now())
                ->visible(fn (Get $get) => $get('type') !== 'company'),
            Forms\Components\TextInput::make('passport_no')->label(__('Passport no.'))->visible(fn (Get $get) => $get('type') !== 'company'),
            Forms\Components\DatePicker::make('passport_expiry')->label(__('Passport valid until'))->native(false)->displayFormat('d.m.Y')
                ->visible(fn (Get $get) => ! $compact && $get('type') !== 'company'),
            Forms\Components\TextInput::make('tax_number')->label(__('Tax no. (ЕДБ)'))->visible(fn (Get $get) => $get('type') === 'company'),
            Forms\Components\TextInput::make('company_number')->label(__('Company reg. no. (ЕМБС)'))->visible(fn (Get $get) => ! $compact && $get('type') === 'company'),
            Forms\Components\TextInput::make('bank_account')->label(__('Bank account'))->visible(fn (Get $get) => ! $compact && $get('type') === 'company'),
            Forms\Components\TextInput::make('phone')->label(__('Phone'))->tel(),
            Forms\Components\TextInput::make('email')->label(__('E-mail'))->email(),
            Forms\Components\TextInput::make('address')->label(__('Address')),
            Forms\Components\TextInput::make('city')->label(__('City')),
            Forms\Components\TextInput::make('country')->label(__('Country'))->visible(! $compact),
            Forms\Components\Textarea::make('notes')->label(__('Comment'))->rows(2)->columnSpanFull()->visible(! $compact),
            Forms\Components\Toggle::make('is_problem')->label(__('Problem client (warning)'))->visible(! $compact),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([AgencyField::select(), ...static::fields()]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->label(__('Name'))->weight('medium')
                    ->state(fn (Client $r) => $r->display_name)
                    ->icon(fn (Client $r) => $r->type === 'company' ? 'heroicon-m-building-office' : ($r->is_problem ? 'heroicon-m-exclamation-triangle' : null))
                    ->iconColor(fn (Client $r) => $r->is_problem ? 'danger' : 'gray')
                    ->searchable(query: fn (Builder $query, string $search) => $query->search($search))
                    ->sortable(['last_name', 'company_name']),
                Tables\Columns\TextColumn::make('gender')->label(__('Gender'))->toggleable(),
                Tables\Columns\TextColumn::make('birth_date')->label(__('Date of birth'))->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('passport_no')->label(__('Passport no.'))->toggleable(),
                Tables\Columns\TextColumn::make('tax_number')->label(__('Tax no.'))->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')->label(__('Phone')),
                Tables\Columns\TextColumn::make('city')->label(__('City'))->toggleable(),
                Tables\Columns\TextColumn::make('bookings_count')->label(__('Bookings'))->counts('bookings')->badge()->color('gray'),
                AgencyField::column(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label(__('Type'))->options(['person' => __('Persons'), 'company' => __('Companies')]),
                Tables\Filters\TernaryFilter::make('is_problem')->label(__('Problem clients')),
            ])
            ->actions([Tables\Actions\EditAction::make()->iconButton()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->paginated([25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [ClientResource\RelationManagers\BookingsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    /** Options for client selects: search by name / passport / phone */
    public static function searchOptions(?string $search, ?string $type = null, int $limit = 30): array
    {
        return Client::query()->when($type, fn ($q) => $q->where('type', $type))->search($search)->orderBy('last_name')->limit($limit)->get()
            ->mapWithKeys(fn (Client $c) => [$c->id => $c->label()])->all();
    }
}
