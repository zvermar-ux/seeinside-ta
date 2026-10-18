<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\ChatQuestionnaireResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\ChatQuestionnaire;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** "Help me choose" questionnaires in the live chat (winter 2027, summer, spa…) */
class ChatQuestionnaireResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = ChatQuestionnaire::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'ai';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'chat-questionnaires';

    public static function permissionModule(): string
    {
        return 'live_chat';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Questionnaire'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Chat questionnaires');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(4)->schema([
                AgencyField::select(),
                Forms\Components\TextInput::make('icon')->label(__('Icon (emoji)'))->maxLength(4)->placeholder('⛷️'),
                Forms\Components\Select::make('category')->label(__('Category'))->options(ChatQuestionnaire::categories()),
                Forms\Components\CheckboxList::make('offer_types')->label(__('Recommend from'))->columns(2)->columnSpan(2)
                    ->options(['tour' => __('Tours'), 'event' => __('Events'), 'accommodation' => __('Hotels'), 'visa' => __('Visas')])
                    ->helperText(__('Empty = everything. Only active offers are ever recommended.')),
                Forms\Components\TextInput::make('title.mk')->label(__('Title').' (МК)')->required()->maxLength(120)->columnSpan(2)->placeholder('Каде на зимување 2027?'),
                Forms\Components\TextInput::make('title.en')->label(__('Title').' (EN)')->maxLength(120)->columnSpan(2),
                Forms\Components\Textarea::make('intro.mk')->label(__('Intro').' (МК)')->rows(2)->columnSpan(2),
                Forms\Components\Textarea::make('intro.en')->label(__('Intro').' (EN)')->rows(2)->columnSpan(2),
                Forms\Components\FileUpload::make('image')->label(__('Image'))->image()->disk('public')->directory('chat')->maxSize(4096)->columnSpan(2),
                Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true)->inline(false),
            ]),
            Forms\Components\Section::make(__('Questions'))->schema([
                Forms\Components\Repeater::make('questions')->hiddenLabel()->required()->minItems(1)->maxItems(12)->collapsible()->reorderable()->cloneable()
                    ->itemLabel(fn (array $state) => $state['label']['mk'] ?? null)
                    ->addActionLabel(__('Add question'))
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('label.mk')->label(__('Question').' (МК)')->required()->columnSpan(2)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Get $get) => blank($get('key')) ? $set('key', Str::slug(Str::limit((string) $state, 30, ''), '_') ?: 'q'.rand(10, 99)) : null),
                        Forms\Components\TextInput::make('label.en')->label(__('Question').' (EN)')->columnSpan(2),
                        Forms\Components\Select::make('type')->label(__('Answer'))->required()->default('single')->live()
                            ->options(['single' => __('One choice'), 'multi' => __('Several choices'), 'text' => __('Free text')]),
                        Forms\Components\TextInput::make('key')->label(__('Field key'))->required()->alphaDash(),
                        Forms\Components\Repeater::make('options')->label(__('Options'))->columnSpanFull()->grid(2)->defaultItems(0)->columns(2)
                            ->visible(fn (Get $get) => $get('type') !== 'text')
                            ->schema([
                                Forms\Components\TextInput::make('mk')->hiddenLabel()->placeholder('МК')->required(),
                                Forms\Components\TextInput::make('en')->hiddenLabel()->placeholder('EN'),
                            ])
                            ->addActionLabel(__('Add option')),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label(''),
                Tables\Columns\TextColumn::make('title')->label(__('Title'))->weight('semibold')
                    ->state(fn (ChatQuestionnaire $r) => $r->label())
                    ->description(fn (ChatQuestionnaire $r) => trans_choice(':count question|:count questions', count($r->questions ?? []), ['count' => count($r->questions ?? [])])),
                Tables\Columns\TextColumn::make('category')->label(__('Category'))->badge()->formatStateUsing(fn ($state) => ChatQuestionnaire::categories()[$state] ?? $state),
                Tables\Columns\IconColumn::make('made_by_ai')->label('AI')->boolean()->trueIcon('heroicon-o-sparkles')->falseIcon('heroicon-o-user'),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
                AgencyField::column(),
            ])
            ->actions([Tables\Actions\EditAction::make()->iconButton(), \App\Filament\Support\Duplicate::action(), Tables\Actions\DeleteAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatQuestionnaires::route('/'),
            'create' => Pages\CreateChatQuestionnaire::route('/create'),
            'edit' => Pages\EditChatQuestionnaire::route('/{record}/edit'),
        ];
    }
}
