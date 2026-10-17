<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\TourOperatorResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\TourOperator;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TourOperatorResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = TourOperator::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'partners';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionModule(): string
    {
        return 'tour_operators';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Tour operator'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tour operators');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                AgencyField::select(),
                Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                Forms\Components\TextInput::make('contact_person')->label(__('Contact person')),
                Forms\Components\TextInput::make('email')->label(__('E-mail'))->email(),
                Forms\Components\TextInput::make('phone')->label(__('Phone'))->tel(),
                Forms\Components\TextInput::make('default_markup')->label(__('Default markup'))->numeric()->suffix('%')->default(0),
                Forms\Components\Toggle::make('is_own')->label(__('Own offer'))->helperText(__('Arrangements created by the agency itself.')),
                Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
                Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (TourOperator $r) => $r->is_own ? __('Own offer') : $r->contact_person),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('email')->label(__('E-mail'))->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')->label(__('Phone'))->toggleable(),
                Tables\Columns\TextColumn::make('default_markup')->label(__('Markup'))->suffix('%')->alignEnd(),
                Tables\Columns\TextColumn::make('travel_objects_count')->counts('travelObjects')->label(__('Arrangements & hotels'))
                    ->badge()->color('info')->sortable()
                    ->url(fn (TourOperator $r) => TravelObjectResource::getUrl('index', ['tableFilters' => ['tour_operator_id' => ['value' => $r->id]]])),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([AgencyField::filter()])
            ->actions([Tables\Actions\EditAction::make(), Duplicate::action()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTourOperators::route('/'),
            'create' => Pages\CreateTourOperator::route('/create'),
            'edit' => Pages\EditTourOperator::route('/{record}/edit'),
        ];
    }
}
