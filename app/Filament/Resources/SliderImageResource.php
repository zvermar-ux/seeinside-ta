<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\SliderImageResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\SliderImage;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SliderImageResource extends Resource
{
    use GuardedByPermission, Translatable;

    protected static ?string $model = SliderImage::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 4;

    public static function permissionModule(): string
    {
        return 'slider';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Slider image'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Homepage slider');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                AgencyField::select(),
                Forms\Components\FileUpload::make('path')->label(__('Image'))->image()->imageEditor()
                    ->disk('public')->directory('slider')->required()->columnSpanFull(),
                Forms\Components\TextInput::make('title')->label(__('Title')),
                Forms\Components\TextInput::make('link')->label(__('Link'))->url(),
                Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('path')->label('')->disk('public')->height(70)->width(140)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:.5rem']),
                Tables\Columns\TextColumn::make('title')->label(__('Title'))->placeholder('—'),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('link')->label(__('Link'))->limit(40)->toggleable(),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->filters([AgencyField::filter()])
            ->actions([Tables\Actions\EditAction::make(), Duplicate::action(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSliderImages::route('/'),
            'create' => Pages\CreateSliderImage::route('/create'),
            'edit' => Pages\EditSliderImage::route('/{record}/edit'),
        ];
    }
}
