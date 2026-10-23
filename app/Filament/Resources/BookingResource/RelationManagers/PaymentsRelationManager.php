<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\Reports\OfferReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Client payments (instalments, refunds) of a booking */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $icon = 'heroicon-o-banknotes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payments');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Payment'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payments');
    }

    public function isReadOnly(): bool
    {
        $u = Auth::user();

        return ! ($u?->isSuperAdmin() || $u?->can('bookings.manage'));
    }

    public function form(Form $form): Form
    {
        /** @var Booking $b */
        $b = $this->getOwnerRecord();

        $base = \App\Models\Agency::find($b->agency_id)?->doc('base_currency', 'MKD') ?? 'MKD';
        $currencies = array_unique([$b->currency ?: 'EUR', $base, 'EUR']);

        return $form->columns(2)->schema([
            Forms\Components\Grid::make(3)->columnSpanFull()->schema([
                Forms\Components\TextInput::make('original_amount')->label(__('Amount'))->numeric()->required()->live(onBlur: true)
                    ->default(fn () => $b->balance() ?: null)
                    ->helperText(__('Use a negative amount for a refund.')),
                Forms\Components\Select::make('currency')->label(__('Currency'))->options(array_combine($currencies, $currencies))->default($b->currency ?: 'EUR')->live()->selectablePlaceholder(false),
                Forms\Components\TextInput::make('exchange_rate')->label(__('Rate'))->numeric()->step(0.0001)
                    ->visible(fn (Forms\Get $get) => $get('currency') !== $b->currency)
                    ->default(fn () => (float) ($b->exchange_rate ?: \App\Models\Agency::find($b->agency_id)?->eurRate()))
                    ->helperText(fn (Forms\Get $get) => filled($get('original_amount')) && (float) $get('exchange_rate') > 0
                        ? '= '.number_format($get('currency') === $base ? (float) $get('original_amount') / (float) $get('exchange_rate') : (float) $get('original_amount') * (float) $get('exchange_rate'), 2, ',', '.').' '.$b->currency
                        : null),
            ]),
            Forms\Components\DatePicker::make('paid_at')->label(__('Date'))->native(false)->displayFormat('d.m.Y')->default(now())->required(),
            Forms\Components\Select::make('method')->label(__('Payment method'))->options(BookingPayment::methods())->default('cash')
                ->helperText(__('Cash and card payments automatically get a cash receipt (Каса прими).')),
            Forms\Components\Select::make('invoice_id')->label(__('For invoice / proforma'))->placeholder('—')
                ->options(fn () => $b->invoices()->latest('id')->get()->mapWithKeys(fn ($i) => [$i->id => $i->title().' · '.number_format((float) $i->total, 2, ',', '.').' '.$i->currency])),
            Forms\Components\TextInput::make('reference')->label(__('Receipt / reference no.'))->maxLength(190),
            Forms\Components\Textarea::make('note')->label(__('Note'))->rows(2)->columnSpanFull(),
        ]);
    }

    /** same currency as the booking: the amount is the entered amount */
    protected function normalize(array $data): array
    {
        /** @var Booking $b */
        $b = $this->getOwnerRecord();
        $data['currency'] = $data['currency'] ?? $b->currency;
        if ($data['currency'] === $b->currency) {
            $data['amount'] = $data['original_amount'];
            $data['exchange_rate'] = null;
        }

        return $data;
    }

    public function table(Table $table): Table
    {
        /** @var Booking $b */
        $b = $this->getOwnerRecord();

        return $table
            ->defaultSort('paid_at')
            ->description(fn () => __('Total').': '.OfferReport::money($b->total, $b->currency).' · '.__('Paid').': '.OfferReport::money($b->fresh()->paid_amount, $b->currency).' · '.__('Owes').': '.OfferReport::money($b->fresh()->balance(), $b->currency))
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')->label(__('Date'))->date('d.m.Y'),
                Tables\Columns\TextColumn::make('amount')->label(__('Amount'))->alignEnd()->weight('medium')
                    ->formatStateUsing(fn (BookingPayment $r) => OfferReport::money($r->amount, $b->currency))
                    ->color(fn (BookingPayment $r) => (float) $r->amount < 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('original_amount')->label(__('Paid in'))->alignEnd()->toggleable()
                    ->formatStateUsing(fn (BookingPayment $r) => $r->currency && $r->currency !== $b->currency ? OfferReport::money($r->original_amount, $r->currency).' @ '.(float) $r->exchange_rate : '')
                    ->placeholder(''),
                Tables\Columns\TextColumn::make('invoice.number')->label(__('Invoice'))->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('method')->label(__('Payment method'))->formatStateUsing(fn ($state) => BookingPayment::methods()[$state] ?? $state)->placeholder('—'),
                Tables\Columns\TextColumn::make('reference')->label(__('Receipt / reference no.'))->placeholder('—'),
                Tables\Columns\TextColumn::make('note')->label(__('Note'))->limit(40)->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')->label(__('Entered by'))->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label(__('Add payment'))->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(fn (array $data) => $this->normalize($data))
                    ->after(fn () => $this->dispatch('refresh-booking')),
            ])
            ->actions([
                Tables\Actions\Action::make('receipt')->iconButton()->icon('heroicon-o-printer')->tooltip(__('Print cash receipt'))
                    ->visible(fn (BookingPayment $r) => (bool) $r->cashEntry)
                    ->url(fn (BookingPayment $r) => \App\Services\Documents\Documents::url('cash', $r->cashEntry->id), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()->iconButton()
                    ->mutateRecordDataUsing(function (array $data) use ($b) {
                        $data['original_amount'] ??= $data['amount'];
                        $data['currency'] ??= $b->currency;

                        return $data;
                    })
                    ->mutateFormDataUsing(fn (array $data) => $this->normalize($data))
                    ->after(fn () => $this->dispatch('refresh-booking')),
                Tables\Actions\DeleteAction::make()->iconButton()->after(fn () => $this->dispatch('refresh-booking')),
            ])
            ->emptyStateHeading(__('No payments yet'))
            ->paginated(false);
    }
}
