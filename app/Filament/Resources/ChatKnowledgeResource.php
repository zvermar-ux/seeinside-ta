<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\ChatKnowledgeResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\ChatKnowledge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** What the chatbot knows: entered by operators, made by the website analysis, corrected answers */
class ChatKnowledgeResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = ChatKnowledge::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'ai';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'bot-knowledge';

    public static function permissionModule(): string
    {
        return 'live_chat';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Knowledge entry'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bot knowledge');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                AgencyField::select(),
                Forms\Components\TextInput::make('title')->label(__('Topic / question'))->required()->maxLength(190)->columnSpan(2)
                    ->placeholder('Како се плаќа аранжманот?'),
                Forms\Components\Textarea::make('content')->label(__('What the bot should know / answer'))->required()->rows(8)->autosize()->columnSpanFull()
                    ->placeholder('Аванс 30% при резервација, остатокот најдоцна 14 дена пред патувањето. Плаќање во готово, со картичка во агенција или на рати со…'),
                Forms\Components\Select::make('source')->label(__('Source'))->options(ChatKnowledge::sources())->default('manual')->disabled()->dehydrated(false),
                Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label(__('Topic / question'))->weight('semibold')->searchable(['title', 'content'])->wrap()
                    ->description(fn (ChatKnowledge $r) => \Illuminate\Support\Str::limit($r->content, 140)),
                Tables\Columns\TextColumn::make('source')->label(__('Source'))->badge()
                    ->formatStateUsing(fn (string $state) => ChatKnowledge::sources()[$state] ?? $state)
                    ->color(fn (string $state) => ['manual' => 'primary', 'analysis' => 'info', 'correction' => 'warning'][$state] ?? 'gray'),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
                Tables\Columns\TextColumn::make('updated_at')->label(__('Changed'))->since()->sortable(),
                AgencyField::column(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')->label(__('Source'))->options(ChatKnowledge::sources()),
                AgencyField::filter(),
            ])
            ->actions([Tables\Actions\EditAction::make()->iconButton(), Tables\Actions\DeleteAction::make()->iconButton()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatKnowledge::route('/'),
            'create' => Pages\CreateChatKnowledge::route('/create'),
            'edit' => Pages\EditChatKnowledge::route('/{record}/edit'),
        ];
    }
}
