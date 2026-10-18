<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\ChatConversationResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\ChatConversation;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChatConversationResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = ChatConversation::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'ai';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'live-chat';

    public static function permissionModule(): string
    {
        return 'live_chat';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Conversation'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Conversations');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('channel', 'ai');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('status', 'open')->where('needs_human', true)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Waiting for an agent');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->poll('15s')
            ->recordUrl(fn (ChatConversation $r) => static::getUrl('view', ['record' => $r]))
            ->columns([
                Tables\Columns\IconColumn::make('needs_human')->label('')->boolean()->trueIcon('heroicon-s-hand-raised')->falseIcon('heroicon-o-cpu-chip')
                    ->trueColor('danger')->falseColor('gray')->tooltip(fn (ChatConversation $r) => $r->needs_human ? __('Waiting for an agent') : __('Bot')),
                Tables\Columns\TextColumn::make('name')->label(__('Visitor'))->weight('semibold')
                    ->state(fn (ChatConversation $r) => $r->displayName())
                    ->description(fn (ChatConversation $r) => implode(' · ', array_filter([$r->phone, $r->email]))),
                Tables\Columns\TextColumn::make('last')->label(__('Last message'))->wrap()->limit(90)
                    ->state(fn (ChatConversation $r) => $r->messages()->whereIn('role', ['visitor', 'bot', 'operator'])->latest('id')->value('body'))
                    ->weight(fn (ChatConversation $r) => $r->hasUnread() ? 'bold' : null),
                Tables\Columns\TextColumn::make('messages_count')->label(__('Messages'))->numeric()->toggleable(),
                Tables\Columns\TextColumn::make('questionnaire.title')->label(__('Questionnaire'))->toggleable()
                    ->formatStateUsing(fn ($state, ChatConversation $r) => $r->questionnaire?->label()),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (string $state, ChatConversation $r) => $r->bot_paused && $state === 'open' ? __('Agent') : ($state === 'open' ? __('Open') : __('Closed')))
                    ->color(fn (string $state, ChatConversation $r) => $state === 'closed' ? 'gray' : ($r->bot_paused ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('cost')->label('AI $')->numeric(4)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_message_at')->label(__('Time'))->since()->sortable(),
                AgencyField::column(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('needs_human')->label(__('Waiting for an agent')),
                Tables\Filters\SelectFilter::make('status')->label(__('Status'))->options(['open' => __('Open'), 'closed' => __('Closed')]),
                Tables\Filters\Filter::make('contact')->label(__('Left contact'))->query(fn (Builder $query) => $query->where(fn ($w) => $w->whereNotNull('phone')->orWhereNotNull('email'))),
                AgencyField::filter(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->label(__('Open'))->icon('heroicon-o-chat-bubble-left-ellipsis')->iconButton()
                    ->url(fn (ChatConversation $r) => static::getUrl('view', ['record' => $r])),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
