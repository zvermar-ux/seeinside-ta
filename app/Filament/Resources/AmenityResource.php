<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AmenityResource\Pages;
use App\Models\Amenity;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AmenityResource extends Resource
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Amenity::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 3;

    public static function can(string $action, ?Model $record = null): bool
    {
        $user = Auth::user();

        return $user && ($user->isSuperAdmin() || (in_array($action, ['viewAny', 'view'], true) && $user->can('catalog_settings.view')));
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Amenity'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Hotel amenities');
    }

    public const CATEGORIES = ['general' => 'General', 'room' => 'Room', 'wellness' => 'Wellness', 'family' => 'Family', 'sport' => 'Sport', 'food' => 'Food & drink', 'transport' => 'Transport'];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name.mk')->label(__('Name').' (МК)')->required(),
            Forms\Components\TextInput::make('name.en')->label(__('Name').' (EN)')->required(),
            Forms\Components\TextInput::make('key')->label(__('Field key'))->required()->alphaDash()->unique(ignoreRecord: true),
            Forms\Components\Select::make('category')->label(__('Category'))->options(collect(self::CATEGORIES)->map(fn ($l) => __($l)))->default('general'),
            Forms\Components\TextInput::make('icon')->label(__('Icon'))->placeholder('heroicon-o-wifi')
                ->helperText(new \Illuminate\Support\HtmlString('<a href="https://heroicons.com" target="_blank" style="text-decoration:underline">heroicons.com</a>')),
            Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')->defaultSort('sort_order')->paginated(false)
            ->columns([
                Tables\Columns\IconColumn::make('icon')->label('')->icon(fn ($state) => $state ?: 'heroicon-o-check'),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->weight('semibold')
                    ->state(fn (Amenity $r) => $r->tr('name'))->description(fn (Amenity $r) => $r->key),
                Tables\Columns\TextColumn::make('category')->label(__('Category'))->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => __(self::CATEGORIES[$state] ?? $state)),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active'))->disabled(fn () => ! Auth::user()->isSuperAdmin()),
            ])
            ->actions([Tables\Actions\EditAction::make(), Duplicate::action()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAmenities::route('/')];
    }
}
