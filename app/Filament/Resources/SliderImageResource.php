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
                    ->imageEditorAspectRatios(['16:9', '21:9', '3:1', null])
                    ->disk('public')->directory('slider')->required()->maxSize(10240)->columnSpanFull(),
            ]),
            Forms\Components\Section::make(__('Text on the slide'))->icon('heroicon-o-language')
                ->description(__('Use the language switcher at the top to enter the English version.'))
                ->columns(2)->schema([
                    Forms\Components\TextInput::make('supertitle')->label(__('Supertitle'))->placeholder('LAST MINUTE')
                        ->helperText(__('Small text above the title.'))->maxLength(120),
                    Forms\Components\TextInput::make('title')->label(__('Title'))->placeholder('Летувајте во Грција')->maxLength(160),
                    Forms\Components\Textarea::make('subtitle')->label(__('Subtitle'))->rows(2)->columnSpanFull()->maxLength(400)
                        ->placeholder('Хотели на плажа, со превоз од Скопје'),
                ]),
            Forms\Components\Section::make(__('Button'))->icon('heroicon-o-cursor-arrow-rays')->columns(3)->schema([
                Forms\Components\TextInput::make('button_label')->label(__('Button text'))->placeholder('Види понуда')->maxLength(60),
                Forms\Components\TextInput::make('link')->label(__('Button link'))->placeholder('https://… или /grcija')
                    ->helperText(__('Without button text, the whole slide is a link.'))->maxLength(500),
                Forms\Components\Toggle::make('open_in_new_tab')->label(__('Open in a new tab'))->inline(false),
            ]),
            Forms\Components\Section::make()->schema([
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
                Tables\Columns\TextColumn::make('title')->label(__('Title'))->placeholder('—')->weight('semibold')->wrap()
                    ->description(fn (SliderImage $r) => $r->supertitle ?: null),
                Tables\Columns\TextColumn::make('subtitle')->label(__('Subtitle'))->placeholder('—')->limit(60)->toggleable(),
                Tables\Columns\TextColumn::make('button_label')->label(__('Button'))->placeholder('—')->badge()->color('gray')
                    ->description(fn (SliderImage $r) => $r->link ? \Illuminate\Support\Str::limit($r->link, 40) : null),
                AgencyField::column(),
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
