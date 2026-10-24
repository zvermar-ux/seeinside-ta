<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Http\Controllers\PortalController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Message;
use App\Services\Messaging\Channels;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\Templates;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** All communication with the client of a booking: portal messages, e-mails, WhatsApp, Viber */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $icon = 'heroicon-o-chat-bubble-left-right';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Communication');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = Message::withoutGlobalScopes()->where('booking_id', $ownerRecord->getKey())->where('direction', 'in')->whereNull('read_at')->count();

        return $n ? (string) $n : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function isReadOnly(): bool
    {
        $u = Auth::user();

        return ! ($u?->isSuperAdmin() || $u?->can('bookings.manage'));
    }

    public function mount(): void
    {
        parent::mount();
        // opening the tab = the client's messages are read
        Message::withoutGlobalScopes()->where('booking_id', $this->getOwnerRecord()->getKey())->where('direction', 'in')->whereNull('read_at')->update(['read_at' => now()]);
    }

    protected function channelOptions(Agency $a, bool $withPortal = true): array
    {
        $opts = $withPortal ? ['portal' => __('Client portal')] : [];
        foreach (['email' => 'E-mail', 'whatsapp' => 'WhatsApp', 'viber' => 'Viber'] as $k => $l) {
            if (Channels::enabled($a, $k)) {
                $opts[$k] = $l;
            }
        }

        return $opts;
    }

    public function table(Table $table): Table
    {
        /** @var Booking $b */
        $b = $this->getOwnerRecord();
        $a = Agency::find($b->agency_id);
        $events = Templates::events();

        return $table
            ->defaultSort('created_at', 'desc')
            ->description(fn () => __('Client portal').': '.PortalController::url($b))
            ->columns([
                Tables\Columns\IconColumn::make('direction')->label('')->width('1%')
                    ->icon(fn ($state) => $state === 'in' ? 'heroicon-m-arrow-down-left' : 'heroicon-m-arrow-up-right')
                    ->color(fn ($state) => $state === 'in' ? 'warning' : 'gray')
                    ->tooltip(fn ($state) => $state === 'in' ? __('From the client') : __('To the client')),
                Tables\Columns\TextColumn::make('created_at')->label(__('Date'))->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('channel')->label(__('Channel'))->badge()
                    ->formatStateUsing(fn ($state) => Message::channels()[$state] ?? $state)
                    ->color(fn ($state) => ['whatsapp' => 'success', 'viber' => 'info', 'portal' => 'warning'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('body')->label(__('Message'))->wrap()->limit(160)
                    ->description(fn (Message $r) => $r->event ? ($events[str_replace('agency:', '', $r->event)][0] ?? $r->event) : ($r->author?->name)),
                Tables\Columns\TextColumn::make('recipient')->label(__('To'))->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn ($state, Message $r) => match ($state) { 'sent' => $r->read_at ? __('Read') : __('Sent'), 'failed' => __('Failed'), 'received' => __('Received'), default => $state })
                    ->color(fn ($state) => ['sent' => 'success', 'failed' => 'danger', 'received' => 'warning'][$state] ?? 'gray')
                    ->tooltip(fn (Message $r) => $r->error),
            ])
            ->headerActions([
                Tables\Actions\Action::make('write')->label(__('Write to the client'))->icon('heroicon-o-pencil-square')
                    ->form([
                        Forms\Components\Textarea::make('body')->label(__('Message'))->rows(5)->required(),
                        Forms\Components\CheckboxList::make('channels')->label(__('Send over'))->options($this->channelOptions($a))->default(['portal', 'email'])->required()->columns(4)
                            ->helperText(__('The message always stays in the client portal; e-mail / WhatsApp / Viber tell the client about it.')),
                    ])
                    ->action(function (array $data) use ($b, $a) {
                        $channels = (array) $data['channels'];
                        Message::withoutGlobalScopes()->create([
                            'agency_id' => $b->agency_id, 'booking_id' => $b->id, 'client_id' => $b->client_id, 'direction' => 'out', 'channel' => 'portal',
                            'body' => $data['body'], 'status' => 'sent', 'created_by' => Auth::id(),
                        ]);
                        $other = array_values(array_diff($channels, ['portal']));
                        $n = $other ? Messenger::notify('agency_reply', $b->fresh(), null, ['{message}' => $data['body']], channels: $other) : 0;
                        $failed = count($other) - $n;
                        $failed > 0
                            ? Notification::make()->warning()->title(__('Saved in the portal; :n channel(s) failed – see the status.', ['n' => $failed]))->send()
                            : Notification::make()->success()->title(__('Message sent'))->send();
                    }),
                Tables\Actions\Action::make('notify')->label(__('Send a notification'))->icon('heroicon-o-bell')->color('gray')
                    ->form([
                        Forms\Components\Select::make('event')->label(__('Notification'))->required()
                            ->options(collect($events)->filter(fn ($e) => $e[1] === 'client')->except('agency_reply')->map(fn ($e) => $e[0])->all()),
                        Forms\Components\CheckboxList::make('channels')->label(__('Send over'))->options($this->channelOptions($a, false))->default(['email'])->required()->columns(3),
                    ])
                    ->action(function (array $data) use ($b) {
                        $n = Messenger::notify($data['event'], $b->fresh(), $b->invoices()->latest('id')->first(), channels: (array) $data['channels']);
                        $n ? Notification::make()->success()->title(__('Sent'))->send()
                            : Notification::make()->danger()->title(__('Not sent – see the status in the list.'))->send();
                    }),
                Tables\Actions\Action::make('wa')->label('WhatsApp')->icon('heroicon-o-chat-bubble-oval-left')->color('success')
                    ->visible(fn () => (bool) Channels::clickLinks($b->customer_phone, '', $a))
                    ->url(fn () => Channels::clickLinks($b->customer_phone, __('Hello').' '.$b->customer_name.', '.$b->reference.': '.PortalController::url($b), $a)['whatsapp'] ?? null, shouldOpenInNewTab: true),
                Tables\Actions\Action::make('viber')->label('Viber')->icon('heroicon-o-phone')->color('info')
                    ->visible(fn () => (bool) Channels::clickLinks($b->customer_phone, '', $a))
                    ->url(fn () => Channels::clickLinks($b->customer_phone, __('Hello').' '.$b->customer_name.', '.$b->reference.': '.PortalController::url($b), $a)['viber'] ?? null, shouldOpenInNewTab: true),
            ])
            ->actions([
                Tables\Actions\Action::make('view')->iconButton()->icon('heroicon-o-eye')->modalSubmitAction(false)
                    ->modalHeading(fn (Message $r) => $r->subject ?: __('Message'))
                    ->modalContent(fn (Message $r) => new \Illuminate\Support\HtmlString('<div style="white-space:pre-wrap">'.e($r->body).'</div>'.($r->error ? '<p style="color:#dc2626;margin-top:1rem">'.e($r->error).'</p>' : ''))),
            ])
            ->emptyStateHeading(__('No messages yet'))
            ->emptyStateDescription(__('Messages from the client portal and every e-mail / WhatsApp / Viber notification appear here.'))
            ->paginated([10, 25, 50]);
    }
}
