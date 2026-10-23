<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\CashEntryResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\CashEntry;
use App\Models\Client;
use App\Services\Documents\Documents;
use App\Services\Finance\Finance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Cash register documents: Каса прими (receipt) / Каса исплати (payout) */
class CashEntryResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = CashEntry::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 3;

    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Cash document'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Cash register');
    }

    public static function form(Form $form): Form
    {
        $agency = fn (Get $get) => Agency::find(AgencyField::current($get) ?? Auth::user()->agency_id);

        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                AgencyField::select(),
                Forms\Components\ToggleButtons::make('direction')->label(__('Document'))->inline()->required()->default(fn () => request('direction', 'in'))
                    ->options(['in' => __('Cash receipt (Каса прими)'), 'out' => __('Cash payout (Каса исплати)')])
                    ->colors(['in' => 'success', 'out' => 'danger'])->icons(['in' => 'heroicon-m-arrow-down-tray', 'out' => 'heroicon-m-arrow-up-tray'])
                    ->disabled(fn (?CashEntry $record) => (bool) $record)->columnSpan(2),
                Forms\Components\Placeholder::make('no')->label(__('Number'))->content(fn (?CashEntry $record) => $record?->number() ?? __('Assigned automatically on save')),
                Forms\Components\Select::make('client_id')->label(__('Company / person'))->searchable()->live()
                    ->getSearchResultsUsing(fn (string $search) => ClientResource::searchOptions($search))
                    ->getOptionLabelUsing(fn ($value) => Client::find($value)?->label())
                    ->createOptionForm(ClientResource::fields(true))
                    ->createOptionUsing(function (array $data, Get $get) {
                        $data['agency_id'] = AgencyField::current($get) ?? Auth::user()->agency_id;

                        return Client::create($data)->getKey();
                    })
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $state && $set('party_name', Client::find($state)?->display_name)),
                Forms\Components\TextInput::make('party_name')->label(__('Received from / paid to'))->required(),
                Forms\Components\DateTimePicker::make('entry_at')->label(__('Date'))->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->default(now())->required(),
                Forms\Components\TextInput::make('amount')->label(__('Amount'))->numeric()->required()->minValue(0.01),
                Forms\Components\Select::make('currency')->label(__('Currency'))->options(['MKD' => 'MKD', 'EUR' => 'EUR', 'USD' => 'USD'])->default('MKD')->required()->live(),
                Forms\Components\TextInput::make('exchange_rate')->label(__('Rate'))->numeric()->step(0.0001)
                    ->visible(fn (Get $get) => $get('currency') !== 'MKD')->default(fn (Get $get) => $agency($get)?->eurRate()),
                Forms\Components\Select::make('register')->label(__('Cash register'))->required()->selectablePlaceholder(false)
                    ->options(fn (Get $get) => Finance::registers($agency($get)))
                    ->default(fn (Get $get) => Finance::registerFor('cash', $agency($get))),
                Forms\Components\Select::make('method')->label(__('Payment method'))->options(CashEntry::methods())->default('cash')->required(),
                Forms\Components\Select::make('category')->label(__('Category'))->options(CashEntry::categories())->default('arrangement'),
                Forms\Components\Select::make('booking_id')->label(__('Booking'))->searchable()->columnSpan(2)
                    ->getSearchResultsUsing(fn (string $search) => Booking::query()->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%"))
                        ->latest()->limit(30)->get()->mapWithKeys(fn (Booking $b) => [$b->id => $b->reference.' · '.$b->customer_name.' · '.$b->itemTitle()])->all())
                    ->getOptionLabelUsing(fn ($value) => ($b = Booking::find($value)) ? $b->reference.' · '.$b->customer_name.' · '.$b->itemTitle() : null)
                    ->disabled(fn (?CashEntry $record) => (bool) $record?->booking_payment_id)
                    ->helperText(fn (?CashEntry $record) => $record?->booking_payment_id ? __('Created from a booking payment – change the payment in the booking.') : __('A receipt for a booking is also recorded as a payment of the booking.')),
                Forms\Components\TextInput::make('purpose')->label(__('Purpose'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('entry_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('direction')->label('')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'in' ? __('Receipt') : __('Payout'))->color(fn ($state) => $state === 'in' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('seq')->label(__('No.'))->state(fn (CashEntry $r) => $r->number())->sortable(['year', 'seq']),
                Tables\Columns\TextColumn::make('entry_at')->label(__('Date'))->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('party_name')->label(__('Company / person'))->searchable()->limit(28),
                Tables\Columns\TextColumn::make('purpose')->label(__('Purpose'))->limit(40)->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('category')->label(__('Category'))->formatStateUsing(fn ($state) => CashEntry::categories()[$state] ?? $state)->toggleable(),
                Tables\Columns\TextColumn::make('amount')->label(__('Amount'))->alignEnd()->weight('medium')->sortable()
                    ->formatStateUsing(fn (CashEntry $r) => ($r->direction === 'out' ? '−' : '').InvoiceResource::money($r->amount, $r->currency))
                    ->color(fn (CashEntry $r) => $r->direction === 'in' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('amount_base')->label(__('In MKD'))->alignEnd()->formatStateUsing(fn ($state) => InvoiceResource::money($state))->toggleable(),
                Tables\Columns\TextColumn::make('register')->label(__('Cash register'))
                    ->formatStateUsing(fn ($state, CashEntry $r) => Finance::registers(Agency::find($r->agency_id))[$state] ?? $state)->toggleable(),
                Tables\Columns\TextColumn::make('method')->label(__('Payment method'))->formatStateUsing(fn ($state) => CashEntry::methods()[$state] ?? $state)->toggleable(),
                Tables\Columns\TextColumn::make('booking.reference')->label(__('Booking'))->placeholder('—')->toggleable()
                    ->url(fn (CashEntry $r) => $r->booking_id ? BookingResource::getUrl('edit', ['record' => $r->booking_id]) : null),
                Tables\Columns\TextColumn::make('creator.name')->label(__('Entered by'))->toggleable(isToggledHiddenByDefault: true),
                AgencyField::column(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direction')->label(__('Document'))->options(['in' => __('Receipts'), 'out' => __('Payouts')]),
                Tables\Filters\SelectFilter::make('register')->label(__('Cash register'))->options(fn () => Finance::registers(Agency::find(Auth::user()->agency_id))),
                Tables\Filters\SelectFilter::make('category')->label(__('Category'))->options(CashEntry::categories()),
                Tables\Filters\Filter::make('period')->form([
                    Forms\Components\DatePicker::make('from')->label(__('From'))->native(false)->displayFormat('d.m.Y'),
                    Forms\Components\DatePicker::make('to')->label(__('To'))->native(false)->displayFormat('d.m.Y'),
                ])->columns(2)->query(fn (Builder $q, array $data) => $q
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('entry_at', '>=', $d))
                    ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('entry_at', '<=', $d))),
                AgencyField::filter(),
            ])
            ->actions([
                Tables\Actions\Action::make('print')->iconButton()->icon('heroicon-o-printer')->tooltip(__('Print'))
                    ->url(fn (CashEntry $r) => Documents::url('cash', $r->id), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton()->visible(fn (CashEntry $r) => ! $r->booking_payment_id),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashEntries::route('/'),
            'create' => Pages\CreateCashEntry::route('/create'),
            'edit' => Pages\EditCashEntry::route('/{record}/edit'),
        ];
    }
}
