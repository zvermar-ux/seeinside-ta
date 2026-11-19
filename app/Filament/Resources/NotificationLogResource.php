<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationLogResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Message;
use App\Services\Messaging\Templates;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Every notification sent (e-mail, WhatsApp, Viber): to whom, which, sent or failed – by day, week, month */
class NotificationLogResource extends Resource
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Message::class;

    protected static ?string $slug = 'notification-log';

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 31;

    public static function defaultNavigationLabel(): string
    {
        return __('Notification log');
    }

    public static function getModelLabel(): string
    {
        return __('notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Notification log');
    }

    public static function canAccess(): bool
    {
        $u = Auth::user();

        return (bool) ($u && ($u->isSuperAdmin() || ($u->agency_id && $u->can('agency.manage'))));
    }

    public static function can(string $action, ?Model $record = null): bool
    {
        return in_array($action, ['viewAny', 'view'], true) && static::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('direction', 'out')->with('booking');
    }

    /** "agency:new_booking" / "test:booking_received" → name of the notification */
    public static function eventLabel(?string $e): string
    {
        if (! $e) {
            return __('Written by hand');
        }
        $prefix = '';
        if (str_starts_with($e, 'test:')) {
            $prefix = 'TEST · ';
            $e = substr($e, 5);
        }
        if (str_starts_with($e, 'agency:')) {
            $e = substr($e, 7);
        }
        if ($e === 'test') {
            return __('Test message');
        }

        return $prefix.(Templates::events()[$e][0] ?? $e);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label(__('Sent'))->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('event')->label(__('Notification'))->formatStateUsing(fn ($state) => static::eventLabel($state))->wrap()
                    ->description(fn (Message $r) => str_starts_with((string) $r->event, 'agency:') || in_array(Templates::events()[$r->event][1] ?? '', ['agency'], true) ? __('to the agency') : null),
                Tables\Columns\TextColumn::make('channel')->label(__('Channel'))->badge()->formatStateUsing(fn ($state) => Message::channels()[$state] ?? $state)->color('gray'),
                Tables\Columns\TextColumn::make('recipient')->label(__('To'))->searchable()->copyable(),
                Tables\Columns\TextColumn::make('subject')->label(__('Subject'))->limit(50)->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('booking.reference')->label(__('Booking'))
                    ->url(fn (Message $r) => $r->booking_id ? BookingResource::getUrl('edit', ['record' => $r->booking_id]) : null),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn ($state) => $state === 'sent' ? __('Sent') : ($state === 'failed' ? __('Not sent') : __(ucfirst((string) $state))))
                    ->color(fn ($state) => $state === 'sent' ? 'success' : ($state === 'failed' ? 'danger' : 'gray'))
                    ->tooltip(fn (Message $r) => $r->error),
                AgencyField::column()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('status')->label(__('Status'))->options(['sent' => __('Sent'), 'failed' => __('Not sent')]),
                Tables\Filters\SelectFilter::make('channel')->label(__('Channel'))->options(Message::channels()),
                Tables\Filters\SelectFilter::make('event')->label(__('Notification'))
                    ->options(collect(Templates::events())->mapWithKeys(fn ($v, $k) => [$k => $v[0]])->all())
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null) ? $q->whereIn('event', [$data['value'], 'agency:'.$data['value'], 'test:'.$data['value']]) : $q),
                Tables\Filters\TernaryFilter::make('tests')->label(__('Tests'))->placeholder(__('All'))->trueLabel(__('Only tests'))->falseLabel(__('Without tests'))
                    ->queries(true: fn ($q) => $q->where('event', 'like', 'test%'), false: fn ($q) => $q->where(fn ($q) => $q->whereNull('event')->orWhere('event', 'not like', 'test%'))),
                Tables\Filters\Filter::make('period')->form([
                    Forms\Components\DatePicker::make('from')->label(__('From'))->native(false)->displayFormat('d.m.Y'),
                    Forms\Components\DatePicker::make('until')->label(__('Until'))->native(false)->displayFormat('d.m.Y'),
                ])->query(fn (Builder $q, array $data) => $q
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->iconButton()->modalHeading(fn (Message $r) => static::eventLabel($r->event))
                    ->form([
                        Forms\Components\Placeholder::make('to')->label(__('To'))->content(fn (Message $r) => $r->recipient.' · '.(Message::channels()[$r->channel] ?? $r->channel).' · '.$r->created_at?->format('d.m.Y H:i')),
                        Forms\Components\Placeholder::make('subj')->label(__('Subject'))->content(fn (Message $r) => $r->subject),
                        Forms\Components\Placeholder::make('txt')->label(__('Text'))->content(fn (Message $r) => new \Illuminate\Support\HtmlString(nl2br(e((string) $r->body)))),
                        Forms\Components\Placeholder::make('err')->label(__('Error'))->visible(fn (Message $r) => filled($r->error))->content(fn (Message $r) => $r->error),
                    ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNotificationLog::route('/')];
    }
}
