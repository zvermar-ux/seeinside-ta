<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\CharterResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Charter;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CharterResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Charter::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'region';

    public static function permissionModule(): string
    {
        return 'charters';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Charter'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Charters');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                AgencyField::select(),
                Forms\Components\TextInput::make('region')->label(__('Region'))->required()->columnSpan(2),
                Forms\Components\DatePicker::make('departure_date')->label(__('Departure'))->native(false)->displayFormat('d.m.Y')->required(),
                Forms\Components\DatePicker::make('return_date')->label(__('Return'))->native(false)->displayFormat('d.m.Y')->afterOrEqual('departure_date'),
                Forms\Components\TextInput::make('seats')->label(__('Seats'))->numeric()->minValue(0)->default(0),
                Forms\Components\TextInput::make('price')->label(__('Price'))->numeric()->required(),
                Forms\Components\TextInput::make('transport_only_price')->label(__('Transport only'))->numeric(),
                Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR')->required(),
                Forms\Components\TextInput::make('departure_place')->label(__('Departure place')),
                Forms\Components\TextInput::make('destination')->label(__('Destination')),
                Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true)->inline(false),
                Forms\Components\Textarea::make('note')->label(__('Note'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('region')->label(__('Region'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (Charter $r) => trim($r->departure_place.' → '.$r->destination, ' →')),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('departure_date')->label(__('Departure'))->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('return_date')->label(__('Return'))->date('d.m.Y')->sortable(),
                Tables\Columns\TextInputColumn::make('seats')->label(__('Seats'))->type('number')->rules(['integer', 'min:0'])->width('80px'),
                Tables\Columns\TextColumn::make('price')->label(__('Price'))
                    ->formatStateUsing(fn (Charter $r) => number_format((float) $r->price, 2, ',', '.').' '.$r->currency)->alignEnd()->sortable(),
                Tables\Columns\TextColumn::make('transport_only_price')->label(__('Transport only'))
                    ->formatStateUsing(fn (Charter $r) => $r->transport_only_price ? number_format((float) $r->transport_only_price, 2, ',', '.').' '.$r->currency : '—')->alignEnd(),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                Tables\Filters\Filter::make('upcoming')->label(__('Upcoming only'))->default()
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('departure_date')->orWhereDate('departure_date', '>=', today()))),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Duplicate::action(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('departure_date');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCharters::route('/'),
            'create' => Pages\CreateCharter::route('/create'),
            'edit' => Pages\EditCharter::route('/{record}/edit'),
        ];
    }
}
