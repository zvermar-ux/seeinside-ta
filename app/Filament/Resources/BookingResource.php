<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\DeparturePlace;
use App\Models\Event;
use App\Models\Tour;
use App\Models\User;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BookingResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Booking::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function permissionModule(): string
    {
        return 'bookings';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Booking'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bookings');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getModel()::whereIn('status', [BookingStatus::Inquiry->value, BookingStatus::Pending->value])->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'customer_name', 'customer_email', 'customer_phone'];
    }

    public const TYPES = ['tour' => Tour::class, 'event' => Event::class, 'accommodation' => Accommodation::class, 'charter' => \App\Models\Charter::class];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Group::make()->columnSpan(2)->schema([
                    Forms\Components\Section::make(__('Offer'))->columns(2)->schema([
                        AgencyField::select(),
                        Forms\Components\Select::make('bookable_type')->label(__('Type'))->live()
                            ->options(['tour' => __('Organized tour'), 'event' => __('Event'), 'accommodation' => __('Hotel / apartment'), 'charter' => __('Charter')])
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('bookable_id', null)),
                        Forms\Components\Select::make('bookable_id')->label(__('Offer'))->searchable()->live()
                            ->options(function (Get $get) {
                                $class = self::TYPES[$get('bookable_type')] ?? null;
                                if (! $class) {
                                    return [];
                                }

                                return $class::query()->when(AgencyField::current($get), fn ($q, $a) => $q->where('agency_id', $a))
                                    ->latest()->limit(500)->get()
                                    ->mapWithKeys(fn ($m) => [$m->id => is_array($m->getAttribute('title')) ? $m->tr('title') : $m->name]);
                            }),
                        Forms\Components\Select::make('tour_departure_id')->label(__('Departure date'))
                            ->visible(fn (Get $get) => $get('bookable_type') === 'tour' && $get('bookable_id'))
                            ->options(fn (Get $get) => Tour::find($get('bookable_id'))?->departures->mapWithKeys(fn ($d) => [$d->id => $d->start_date->format('d.m.Y').($d->end_date ? ' – '.$d->end_date->format('d.m.Y') : '')]) ?? []),
                        Forms\Components\Select::make('departure_place_id')->label(__('Departure place'))
                            ->visible(fn (Get $get) => $get('bookable_type') === 'tour')
                            ->options(fn () => DeparturePlace::orderBy('sort_order')->get()->mapWithKeys(fn ($p) => [$p->id => $p->tr('name')])),
                        Forms\Components\Select::make('accommodation_room_id')->label(__('Room'))->live()
                            ->visible(fn (Get $get) => $get('bookable_type') === 'accommodation' && $get('bookable_id'))
                            ->options(fn (Get $get) => Accommodation::find($get('bookable_id'))?->rooms->mapWithKeys(fn ($r) => [$r->id => $r->tr('name').' ('.$r->board?->value.')']) ?? []),
                        Forms\Components\Select::make('event_ticket_id')->label(__('Ticket'))
                            ->visible(fn (Get $get) => $get('bookable_type') === 'event' && $get('bookable_id'))
                            ->options(fn (Get $get) => Event::find($get('bookable_id'))?->tickets->mapWithKeys(fn ($t) => [$t->id => $t->tr('name').' — '.$t->price]) ?? []),
                        Forms\Components\DatePicker::make('date_from')->label(__('From'))->native(false)->displayFormat('d.m.Y')->live(),
                        Forms\Components\DatePicker::make('date_to')->label(__('To'))->native(false)->displayFormat('d.m.Y')->live()->afterOrEqual('date_from'),
                        Forms\Components\Placeholder::make('room_availability')->label(__('Availability'))->columnSpanFull()
                            ->visible(fn (Get $get) => $get('bookable_type') === 'accommodation' && $get('accommodation_room_id') && $get('date_from') && $get('date_to'))
                            ->content(fn (Get $get, ?Booking $record) => static::availabilityHint($get, $record)),
                        Forms\Components\TextInput::make('adults')->label(__('Adults'))->numeric()->default(2)->minValue(0),
                        Forms\Components\TextInput::make('children')->label(__('Children'))->numeric()->default(0)->minValue(0),
                        Forms\Components\TagsInput::make('children_ages')->label(__('Children ages')),
                        Forms\Components\TextInput::make('quantity')->label(__('Quantity (rooms / tickets)'))->numeric()->default(1)->minValue(1)->live(onBlur: true),
                    ]),
                    Forms\Components\Section::make(__('Customer'))->columns(2)->schema([
                        Forms\Components\TextInput::make('customer_name')->label(__('Full name'))->required(),
                        Forms\Components\TextInput::make('customer_email')->label(__('E-mail'))->email(),
                        Forms\Components\TextInput::make('customer_phone')->label(__('Phone'))->tel(),
                        Forms\Components\TextInput::make('customer_city')->label(__('City')),
                        Forms\Components\Repeater::make('passengers')->label(__('Passengers'))->columnSpanFull()->columns(4)->defaultItems(0)
                            ->addActionLabel(__('Add passenger'))
                            ->schema([
                                Forms\Components\TextInput::make('first_name')->label(__('First name')),
                                Forms\Components\TextInput::make('last_name')->label(__('Last name')),
                                Forms\Components\DatePicker::make('birth_date')->label(__('Date of birth'))->native(false)->displayFormat('d.m.Y'),
                                Forms\Components\TextInput::make('passport')->label(__('Passport no.')),
                            ]),
                        Forms\Components\Textarea::make('message')->label(__('Customer message'))->columnSpanFull()->rows(3),
                    ]),
                ]),
                Forms\Components\Section::make(__('Processing'))->columnSpan(1)->schema([
                    Forms\Components\Select::make('status')->label(__('Status'))->options(BookingStatus::class)->required()->default(BookingStatus::Inquiry->value),
                    Forms\Components\Select::make('payment_status')->label(__('Payment'))->options(PaymentStatus::class)->required()->default(PaymentStatus::Unpaid->value)
                        ->helperText(fn (?Booking $record) => $record ? __('Set automatically from the payments below.') : null),
                    Forms\Components\TextInput::make('total')->label(__('Total'))->numeric(),
                    Forms\Components\TextInput::make('paid_amount')->label(fn (?Booking $record) => $record ? __('Paid') : __('Paid now (first payment)'))->numeric()->default(0)
                        ->disabled(fn (?Booking $record) => (bool) $record)->dehydrated(fn (?Booking $record) => ! $record)
                        ->helperText(fn (?Booking $record) => $record ? __('Sum of the payments – add instalments in “Payments” below.') : null),
                    Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR'),
                    Forms\Components\TextInput::make('payment_method')->label(__('Payment method')),
                    Forms\Components\TextInput::make('external_ref')->label(__('Reference in Expert / supplier')),
                    Forms\Components\Select::make('assigned_user_id')->label(__('Assigned to'))
                        ->options(fn () => User::query()->when(! Auth::user()->isSuperAdmin(), fn ($q) => $q->where('agency_id', Auth::user()->agency_id))->pluck('name', 'id'))
                        ->default(fn () => Auth::id()),
                    Forms\Components\Textarea::make('internal_notes')->label(__('Internal notes'))->rows(4),
                    Forms\Components\Placeholder::make('meta')->label(__('Source'))
                        ->content(fn (?Booking $record) => $record ? strtoupper($record->source).' · '.$record->created_at?->format('d.m.Y H:i') : __('Entered by the agency')),
                ]),
            ]),
        ]);
    }

    /** "Free / already booked" hint for the chosen room and dates (this booking itself is not counted) */
    public static function availabilityHint(Get $get, ?Booking $record): \Illuminate\Support\HtmlString
    {
        $acc = Accommodation::find($get('bookable_id'));
        $from = \Carbon\Carbon::parse($get('date_from'));
        $to = \Carbon\Carbon::parse($get('date_to'));
        if (! $acc || $to->lte($from)) {
            return new \Illuminate\Support\HtmlString('—');
        }

        $service = app(\App\Services\Availability\AvailabilityService::class);
        $data = $service->forAccommodation($acc, $from, $to->copy()->subDay(), false);
        $free = $service->freeForStay($data, (int) $get('accommodation_room_id'), $from->toDateString(), $to->toDateString());
        if ($free === null) {
            return new \Illuminate\Support\HtmlString('—');
        }

        $room = $data['rooms'][(int) $get('accommodation_room_id')] ?? null;
        $own = $record?->exists && (int) $record->accommodation_room_id === (int) $get('accommodation_room_id') && ! $room['external']
            && $record->status !== \App\Enums\BookingStatus::Cancelled
            && $record->date_from && $record->date_to && $record->date_from->lt($to) && $record->date_to->gt($from)
            ? max(1, (int) $record->quantity) : 0;
        $free += $own;
        $need = max(1, (int) ($get('quantity') ?: 1));
        $calendar = \App\Filament\Pages\AvailabilityCalendar::getUrl(['accommodation' => $acc->id, 'from' => $from->copy()->subDays(3)->toDateString()]);
        $link = ' <a href="'.e($calendar).'" target="_blank" style="color:rgb(var(--primary-600));font-weight:600">'.e(__('Open calendar')).' ↗</a>';

        return new \Illuminate\Support\HtmlString($free >= $need
            ? '<span style="color:rgb(var(--success-600));font-weight:600">✓ '.e(__('Free (:count units for all nights)', ['count' => $free])).'</span>'.$link
            : '<span style="color:rgb(var(--danger-600));font-weight:600">⚠ '.e(__('Not free for the whole period – overbooking!')).'</span>'.$link);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['bookable', 'departure']))
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label(__('Reference'))->searchable()->weight('semibold')->copyable()
                    ->description(fn (Booking $r) => $r->created_at?->format('d.m.Y H:i')),
                Tables\Columns\TextColumn::make('customer_name')->label(__('Customer'))->searchable()
                    ->description(fn (Booking $r) => $r->customer_phone ?: $r->customer_email),
                Tables\Columns\TextColumn::make('item')->label(__('Offer'))->wrap()
                    ->state(fn (Booking $r) => $r->itemTitle())
                    ->description(fn (Booking $r) => match ($r->bookable_type) {
                        'tour' => __('Organized tour').($r->departure ? ' · '.$r->departure->start_date->format('d.m.Y') : ''),
                        'event' => __('Event'),
                        'accommodation' => __('Hotel / apartment').($r->date_from ? ' · '.$r->date_from->format('d.m.').' – '.$r->date_to?->format('d.m.Y') : ''),
                        'charter' => __('Charter'),
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('pax')->label(__('Persons'))->state(fn (Booking $r) => $r->adults.($r->children ? ' + '.$r->children : '')),
                Tables\Columns\TextColumn::make('total')->label(__('Total'))
                    ->formatStateUsing(fn ($state, Booking $r) => $state ? number_format((float) $state, 2, ',', '.').' '.$r->currency : '—'),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge(),
                Tables\Columns\TextColumn::make('payment_status')->label(__('Payment'))->badge(),
                Tables\Columns\TextColumn::make('source')->label(__('Source'))->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                AgencyField::column()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\SelectFilter::make('status')->label(__('Status'))->options(BookingStatus::class)->multiple(),
                Tables\Filters\SelectFilter::make('payment_status')->label(__('Payment'))->options(PaymentStatus::class),
                Tables\Filters\SelectFilter::make('bookable_type')->label(__('Type'))
                    ->options(['tour' => __('Organized tour'), 'event' => __('Event'), 'accommodation' => __('Hotel / apartment'), 'charter' => __('Charter')]),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')->label(__('Confirm'))->icon('heroicon-o-check')->color('success')->iconButton()->tooltip(__('Confirm'))
                    ->visible(fn (Booking $r) => in_array($r->status, [BookingStatus::Inquiry, BookingStatus::Pending], true))
                    ->requiresConfirmation()
                    ->action(fn (Booking $r) => $r->update(['status' => BookingStatus::Confirmed])),
                Tables\Actions\EditAction::make(),
                Duplicate::action(null, ['external_ref', 'confirmed_at', 'paid_amount', 'status', 'payment_status']),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [BookingResource\RelationManagers\PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
